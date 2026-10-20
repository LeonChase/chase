<?php
namespace App\Tests\Functional;

use App\Entity\{User,Task,TaskInvitation,Property,Lead,PartnerProfile,TeamMembership,ContentPost,Record};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class TaskPlanningTest extends WebTestCase
{
    private KernelBrowser $client;
    private array $users=[];
    private string $today;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();$this->client=self::createClient();$em=$this->em();$schema=new SchemaTool($em);$metadata=$em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        foreach(['sender'=>['ROLE_AGENT'],'recipient'=>['ROLE_AGENT'],'hidden'=>['ROLE_AGENT'],'manager'=>['ROLE_REGION_MANAGER'],'disabled'=>['ROLE_AGENT'],'cross'=>['ROLE_AGENT']] as $id=>$roles){
            $u=new User();$u->id=$id;$u->name='Demo '.$id;$u->email=$id.'@example.test';$u->tenantId=$id==='cross'?'other-tenant':'phpunit-demo';$u->roles=$roles;$u->active=$id!=='disabled';$u->password=password_hash('Isolated test password!',PASSWORD_BCRYPT,['cost'=>4]);$em->persist($u);$this->users[$id]=$u;
        }
        foreach(['recipient','manager','disabled','cross'] as $id)$em->persist(new PartnerProfile(['title'=>'Demo '.$id,'userId'=>$id,'visibleToPartners'=>true],$id,$id==='cross'?'other-tenant':'phpunit-demo'));
        $em->persist(new TeamMembership(['managerId'=>'manager','memberId'=>'sender','active'=>true],'manager','phpunit-demo'));
        $today=new \DateTimeImmutable('today',new \DateTimeZone('Europe/Berlin'));$this->today=$today->format('Y-m-d');
        $records=[
            $this->id(new Property(['title'=>'Privates Demo-Objekt','status'=>'Aktiv'],'sender','phpunit-demo'),'property-own'),
            $this->id(new Lead(['name'=>'Alex Beispiel (Demo)','interest'=>'Verkaufsbewertung','source'=>'Manuelle Demo-Erfassung','status'=>'Neu','notes'=>'PRIVATE LEAD NOTIZ'],'sender','phpunit-demo'),'lead-own'),
            $this->id(new Lead(['name'=>'GEHEIM fremder Kontakt','interest'=>'Fremde Anfrage','status'=>'Neu'],'hidden','phpunit-demo'),'lead-foreign'),
            $this->id(new Task(['title'=>'Demo heute wichtig','status'=>'Offen','priority'=>'Hoch','dueDate'=>$this->today,'propertyId'=>'property-own','leadId'=>'lead-own','notes'=>'PRIVATE AUFGABENNOTIZ'],'sender','phpunit-demo'),'task-main'),
            $this->id(new Task(['title'=>'Demo morgen','status'=>'Offen','priority'=>'Mittel','dueDate'=>$today->modify('+1 day')->format('Y-m-d')],'sender','phpunit-demo'),'task-tomorrow'),
            $this->id(new Task(['title'=>'Demo überfällig','status'=>'Offen','priority'=>'Mittel','dueDate'=>$today->modify('-1 day')->format('Y-m-d')],'sender','phpunit-demo'),'task-overdue'),
            $this->id(new Task(['title'=>'Demo abgeschlossen','status'=>'Erledigt','priority'=>'Hoch','dueDate'=>$today->modify('-2 days')->format('Y-m-d')],'sender','phpunit-demo'),'task-done'),
            $this->id(new Task(['title'=>'GEHEIM fremde Aufgabe','status'=>'Offen','priority'=>'Hoch','dueDate'=>$this->today],'hidden','phpunit-demo'),'task-foreign'),
            $this->id(new Task(['title'=>'GEHEIM anderer Mandant','status'=>'Offen','dueDate'=>$this->today],'sender','other-tenant'),'task-cross'),
        ];
        foreach($records as $r)$em->persist($r);$em->flush();$this->auth();
    }
    private function em(): EntityManagerInterface{return self::getContainer()->get(EntityManagerInterface::class);}
    private function id(Record $r,string $id): Record{$r->id=$id;return $r;}
    private function auth(string $id='sender'): void{$this->client->loginUser($this->users[$id]);}
    private function token(string $url='/tasks'): string{$crawler=$this->client->request('GET',$url);self::assertResponseIsSuccessful();return (string)$crawler->filter('meta[name="portal-csrf"]')->attr('content');}
    private function fresh(string $class,string $id): ?object{$this->em()->clear();return $this->em()->find($class,$id);}
    private function offer(string $recipient='recipient'): TaskInvitation
    {
        $token=$this->token('/tasks/task-main/edit');$this->client->request('POST','/tasks/task-main/offer',['_token'=>$token,'recipientId'=>$recipient]);self::assertResponseRedirects('/tasks/task-main/edit');
        $all=$this->em()->getRepository(TaskInvitation::class)->findAll();self::assertCount(1,$all);return $all[0];
    }

    public function testPlannerExactDateFiltersCalendarViewsAndScope(): void
    {
        $this->client->request('GET','/tasks?date='.$this->today);self::assertResponseIsSuccessful();self::assertSelectorCount(1,'.todo-item');self::assertSelectorTextContains('.todo-list','Demo heute wichtig');self::assertSelectorNotExists('.todo-item[data-task-id="task-tomorrow"]');
        self::assertStringNotContainsString('GEHEIM',$this->client->getResponse()->getContent());self::assertSelectorExists('a.task-day-number[href="/tasks?date='.$this->today.'&view=month"]');
        $this->client->request('GET','/tasks?view=week&date='.$this->today);self::assertResponseIsSuccessful();self::assertSelectorCount(7,'.task-day');
        $this->client->request('GET','/tasks?period=important');self::assertResponseIsSuccessful();self::assertSelectorCount(1,'.todo-item');self::assertSelectorTextContains('.todo-list','Demo heute wichtig');
        $this->client->request('GET','/tasks?period=completed');self::assertResponseIsSuccessful();self::assertSelectorCount(1,'.todo-item');self::assertSelectorTextContains('.todo-list','Demo abgeschlossen');
        $this->client->request('GET','/tasks?period=overdue');self::assertResponseIsSuccessful();self::assertSelectorCount(1,'.todo-item');self::assertSelectorTextContains('.todo-list','Demo überfällig');
        $this->client->request('GET','/tasks?date=2026-02-31');self::assertResponseStatusCodeSame(400);
    }

    public function testTaskOfferValidatesContactsAndDoesNotGrantPrivateRelations(): void
    {
        $token=$this->token('/tasks/task-main/edit');self::assertSelectorExists('select#recipientId option[value="recipient"]');self::assertSelectorNotExists('select#recipientId option[value="hidden"]');self::assertSelectorNotExists('select#recipientId option[value="disabled"]');self::assertSelectorNotExists('select#recipientId option[value="cross"]');
        $this->client->request('POST','/tasks/task-main/offer',['_token'=>'forged','recipientId'=>'recipient']);self::assertResponseStatusCodeSame(403);
        foreach(['hidden','disabled','cross'] as $recipient){$this->client->request('POST','/tasks/task-main/offer',['_token'=>$token,'recipientId'=>$recipient]);self::assertResponseRedirects('/tasks/task-main/edit');}
        self::assertSame(0,$this->em()->getRepository(TaskInvitation::class)->count([]));
        $invitation=$this->offer();$invitationId=$invitation->id;self::assertArrayNotHasKey('propertyId',$invitation->data);self::assertArrayNotHasKey('leadId',$invitation->data);self::assertArrayNotHasKey('notes',$invitation->data);
        $this->client->request('POST','/tasks/task-main/offer',['_token'=>$token,'recipientId'=>'recipient']);self::assertSame(1,$this->em()->getRepository(TaskInvitation::class)->count([]));
        $this->auth('manager');$managerToken=$this->token();self::assertStringNotContainsString($invitationId,$this->client->getResponse()->getContent());$this->client->request('POST','/tasks/invitations/'.$invitationId.'/respond',['_token'=>$managerToken,'decision'=>'accept']);self::assertResponseStatusCodeSame(404);
        $this->auth('recipient');$recipientToken=$this->token();self::assertSelectorTextContains('#task-invitations','Demo heute wichtig');$this->client->request('GET','/tasks/task-main/edit');self::assertResponseStatusCodeSame(404);
        $this->client->request('POST','/tasks/invitations/'.$invitationId.'/respond',['_token'=>$recipientToken,'decision'=>'accept']);self::assertResponseRedirects('/tasks');
        $accepted=$this->fresh(TaskInvitation::class,$invitationId);self::assertSame('accepted',$accepted->data['status']);$task=$this->fresh(Task::class,$accepted->data['acceptedTaskId']);self::assertSame('recipient',$task->ownerId);self::assertSame('Demo heute wichtig',$task->title);self::assertArrayNotHasKey('propertyId',$task->data);self::assertArrayNotHasKey('leadId',$task->data);self::assertSame('sender',$this->fresh(Task::class,'task-main')->ownerId);
        $this->client->request('POST','/tasks/invitations/'.$invitationId.'/respond',['_token'=>$recipientToken,'decision'=>'accept']);self::assertResponseRedirects('/tasks');self::assertSame(1,$this->em()->getRepository(Task::class)->count(['ownerId'=>'recipient']));
        $this->auth();$senderToken=$this->token();self::assertSelectorTextContains('#task-invitations','Angenommen');$this->client->request('POST','/tasks/invitations/'.$invitationId.'/respond',['_token'=>$senderToken,'decision'=>'decline']);self::assertResponseStatusCodeSame(403);
    }

    public function testDeclinedOfferCannotLaterCreateATask(): void
    {
        $id=$this->offer()->id;$this->auth('recipient');$token=$this->token();
        foreach(['decline','accept'] as $decision){$this->client->request('POST','/tasks/invitations/'.$id.'/respond',['_token'=>$token,'decision'=>$decision]);self::assertResponseRedirects('/tasks');}
        self::assertSame('declined',$this->fresh(TaskInvitation::class,$id)->data['status']);self::assertSame(0,$this->em()->getRepository(Task::class)->count(['ownerId'=>'recipient']));
    }

    public function testAcceptedTaskRetainsOnlyExistingExplicitPropertyPermission(): void
    {
        $id=$this->offer('manager')->id;self::assertSame('property-own',$this->fresh(TaskInvitation::class,$id)->data['propertyId']);
        $this->auth('manager');$token=$this->token();$this->client->request('POST','/tasks/invitations/'.$id.'/respond',['_token'=>$token,'decision'=>'accept']);self::assertResponseRedirects('/tasks');
        $invite=$this->fresh(TaskInvitation::class,$id);$task=$this->fresh(Task::class,$invite->data['acceptedTaskId']);self::assertSame('manager',$task->ownerId);self::assertSame('property-own',$task->data['propertyId']);
    }

    public function testChecklistsAndMonthlyRecurrenceArePersistentAndRepeatSafe(): void
    {
        $token=$this->token('/tasks/new?date=2026-01-31');self::assertInputValueSame('dueDate','2026-01-31');
        $data=['_token'=>$token,'title'=>'Demo Monatsroutine','status'=>'Offen','priority'=>'Mittel','dueDate'=>'2026-01-31','recurrence'=>'monthly','checklistText'=>"Unterlagen prüfen\nTermin vorbereiten"];
        $this->client->request('POST','/tasks/new',$data);self::assertResponseRedirects('/tasks');$task=$this->em()->getRepository(Task::class)->findOneBy(['title'=>'Demo Monatsroutine']);$id=$task->id;$stepId=$task->data['checklist'][0]['id'];
        $this->client->request('POST','/tasks/'.$id.'/checklist',['_token'=>'forged','stepId'=>$stepId,'done'=>'1']);self::assertResponseStatusCodeSame(403);
        $this->client->request('POST','/tasks/'.$id.'/checklist',['_token'=>$token,'stepId'=>$stepId,'done'=>'1']);self::assertResponseRedirects('/tasks/'.$id.'/edit#checklist');self::assertTrue($this->fresh(Task::class,$id)->data['checklist'][0]['done']);
        $this->client->request('POST','/tasks/'.$id.'/edit',$data);self::assertResponseRedirects('/tasks');self::assertTrue($this->fresh(Task::class,$id)->data['checklist'][0]['done']);
        foreach(['Erledigt','Erledigt','Offen','Erledigt'] as $status){$this->client->request('POST','/tasks/'.$id.'/status',['_token'=>$token,'status'=>$status]);self::assertResponseRedirects('/tasks');}
        $task=$this->fresh(Task::class,$id);$next=$this->fresh(Task::class,$task->data['nextTaskId']);self::assertSame('2026-02-28',$next->data['dueDate']);self::assertFalse($next->data['checklist'][0]['done']);self::assertSame('Offen',$next->data['status']);self::assertSame(2,$this->em()->getRepository(Task::class)->count(['title'=>'Demo Monatsroutine']));
    }

    public function testCalendarExportIsPrivateAndLocalImportIsIdempotent(): void
    {
        $token=$this->token();$this->client->request('GET','/tasks/calendar.ics');self::assertResponseIsSuccessful();self::assertResponseHeaderSame('Content-Type','text/calendar; charset=UTF-8');$ics=$this->client->getResponse()->getContent();self::assertStringContainsString('Demo heute wichtig',$ics);self::assertStringContainsString('PRIVATE AUFGABENNOTIZ',$ics);self::assertStringNotContainsString('GEHEIM',$ics);
        $this->auth('manager');$this->client->request('GET','/tasks/calendar.ics');self::assertResponseIsSuccessful();self::assertStringNotContainsString('Demo heute wichtig',$this->client->getResponse()->getContent());
        $this->auth();$token=$this->token();$ics="BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:import-demo-1\r\nSUMMARY:Demo importiert\r\nDTSTART;VALUE=DATE:20261026\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        for($i=0;$i<2;++$i){$path=tempnam(sys_get_temp_dir(),'chase-calendar-');file_put_contents($path,$ics);try{$this->client->request('POST','/tasks/calendar/import',['_token'=>$token],['calendar'=>new UploadedFile($path,'demo.ics','text/calendar',null,true)]);self::assertResponseRedirects('/tasks');}finally{if(is_file($path))unlink($path);}}
        self::assertSame(1,$this->em()->getRepository(Task::class)->count(['title'=>'Demo importiert']));$task=$this->em()->getRepository(Task::class)->findOneBy(['title'=>'Demo importiert']);self::assertSame('sender',$task->ownerId);self::assertSame('2026-10-26',$task->data['dueDate']);
    }

    public function testLeadBoardMovesRecordsAndDraftUsesKnownFactsWithoutSending(): void
    {
        $token=$this->token('/leads');self::assertSelectorCount(6,'.pipeline-lane');self::assertSelectorTextContains('.pipeline-board','Alex Beispiel (Demo)');self::assertStringNotContainsString('GEHEIM',$this->client->getResponse()->getContent());self::assertSelectorTextContains('.lead-origin-status','Hausgold · nicht verbunden');
        $this->client->request('POST','/leads/lead-own/status',['_token'=>$token,'status'=>'Termin']);self::assertResponseRedirects('/leads');$lead=$this->fresh(Lead::class,'lead-own');self::assertSame('Termin',$lead->data['status']);self::assertStringContainsString('Neu → Termin',$lead->data['activities'][0]['note']);
        $this->client->request('POST','/leads/lead-own/followup',['_token'=>'forged']);self::assertResponseStatusCodeSame(403);
        $this->client->request('POST','/leads/lead-own/followup',['_token'=>$token]);self::assertResponseRedirects('/leads/lead-own#followup-drafts');$draft=$this->em()->getRepository(ContentPost::class)->findOneBy(['ownerId'=>'sender']);self::assertNotNull($draft);self::assertSame('lead-own',$draft->data['sourceLeadId']);self::assertFalse($draft->data['sent']);self::assertFalse($draft->data['isPublished']);self::assertStringContainsString('Verkaufsbewertung',$draft->data['content']);self::assertStringNotContainsString('PRIVATE LEAD NOTIZ',$draft->data['content']);
        $this->client->request('GET','/leads/lead-own');self::assertResponseIsSuccessful();self::assertSelectorExists('#followup-drafts textarea');self::assertSelectorTextContains('#followup-drafts','Nicht versendet');
        $this->client->request('POST','/leads/lead-foreign/followup',['_token'=>$token]);self::assertResponseStatusCodeSame(404);
    }
}
