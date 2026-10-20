<?php
namespace App\Tests\Unit;

use App\Entity\Task;
use App\Service\CalendarExport;
use PHPUnit\Framework\TestCase;

final class CalendarExportTest extends TestCase
{
    public function testExportIsEscapedFoldedAndRoundTripsWithoutInventedDates(): void
    {
        $calendar=new CalendarExport();
        $task=new Task(['title'=>'Besichtigung; Küche, Balkon '.str_repeat('Ä',55),'dueDate'=>'2026-10-22','notes'=>"Rückfrage\nBEGIN:VEVENT\nName; eins, zwei\\drei",'priority'=>'Hoch','status'=>'In Arbeit'],'owner','tenant');
        $undated=new Task(['title'=>'Ohne Termin'],'owner','tenant');
        $ics=$calendar->export([$task,$undated]);
        self::assertSame(1,substr_count($ics,"\r\nBEGIN:VEVENT\r\n"));
        self::assertStringContainsString('DTSTART;VALUE=DATE:20261022',$ics);
        self::assertStringContainsString('DTEND;VALUE=DATE:20261023',$ics);
        foreach(explode("\r\n",trim($ics)) as $line){self::assertLessThanOrEqual(75,strlen($line));self::assertTrue(mb_check_encoding($line,'UTF-8'));}
        $result=$calendar->import($ics);
        self::assertSame(0,$result['skipped']);self::assertCount(1,$result['events']);
        self::assertSame($task->title,$result['events'][0]['title']);
        self::assertSame($task->data['notes'],$result['events'][0]['notes']);
        self::assertSame('Hoch',$result['events'][0]['priority']);
        self::assertSame('In Arbeit',$result['events'][0]['status']);
        self::assertSame($task->id.'@chase.local',$result['events'][0]['calendarUid']);
    }

    public function testImportConvertsTimezonesAndSkipsUnsupportedOrInvalidEvents(): void
    {
        $ics="BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:utc-1\r\nSUMMARY:Später Termin\r\nDTSTART:20261022T233000Z\r\nBEGIN:VALARM\r\nACTION:EMAIL\r\nSUMMARY:Kein Import von Erinnerungen\r\nEND:VALARM\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nSUMMARY:Ungültiger Tag\r\nDTSTART;VALUE=DATE:20260231\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nSUMMARY:Serie\r\nDTSTART;VALUE=DATE:20261022\r\nRRULE:FREQ=DAILY\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nSUMMARY:Absage\r\nDTSTART;VALUE=DATE:20261022\r\nSTATUS:CANCELLED\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $result=(new CalendarExport())->import($ics);
        self::assertSame(3,$result['skipped']);self::assertCount(1,$result['events']);
        self::assertSame('2026-10-23',$result['events'][0]['dueDate']);
        self::assertSame('Später Termin',$result['events'][0]['title']);
    }

    public function testCalendarWithoutUidGetsStableLocalIdentity(): void
    {
        $calendar=new CalendarExport();$ics="BEGIN:VCALENDAR\nBEGIN:VEVENT\nSUMMARY:Lokaler Termin\nDTSTART;TZID=Europe/Berlin:20261022T100000\nEND:VEVENT\nEND:VCALENDAR\n";
        self::assertSame($calendar->import($ics),$calendar->import($ics));
        self::assertSame('2026-10-22',$calendar->import($ics)['events'][0]['dueDate']);
    }

    public function testMalformedCalendarIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CalendarExport())->import("BEGIN:VCALENDAR\nBEGIN:VEVENT\nSUMMARY:Unvollständig\nEND:VCALENDAR\n");
    }
}
