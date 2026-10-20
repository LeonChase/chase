<?php
namespace App\Controller;

use App\Command\CatalogPreviewCommand;
use App\Entity\{AdvertisingProduct,Document,Record,UserPreference};
use App\Service\{MaterialsImporter,PortalStore};
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\{BinaryFileResponse,HeaderUtils,Request,Response};
use Symfony\Component\Routing\Attribute\Route;

/** Original CHASE catalogue with a private per-user selection and honest local quote drafts. */
final class CatalogController extends PortalController
{
    public function __construct(PortalStore $store,private MaterialsImporter $materials,private CatalogPreviewCommand $renderer,private string $projectDir){parent::__construct($store);}

    #[Route('/advertising',name:'app_advertising',methods:['GET'])]
    public function advertising(Request $request):Response
    {
        $all=$this->products();$cart=$this->cartSelection($all);$favorites=$this->preferenceTargets('favorite');$document=$this->catalogDocument();$catalog=$this->catalogStatus($document);
        $q=mb_substr(trim($request->query->getString('q')),0,200);$category=$request->query->getString('category');$favoriteOnly=$request->query->getBoolean('favorites');$page=max(1,min($catalog['pages'],$request->query->getInt('page',1)));
        $categories=array_values(array_unique(array_filter(array_map(fn($p)=>(string)($p->data['category']??''),$all))));sort($categories);
        $isSearch=$q!==''||$category!==''||$favoriteOnly;
        $items=array_values(array_filter($all,static function(Record $p)use($q,$category,$favoriteOnly,$favorites,$page,$isSearch,$catalog):bool{
            return ($q===''||str_contains(mb_strtolower($p->title.' '.($p->data['description']??'').' '.($p->data['category']??'')),mb_strtolower($q)))&&($category===''||($p->data['category']??'')===$category)&&(!$favoriteOnly||in_array($p->id,$favorites,true))&&($isSearch||!$catalog['available']||(int)($p->data['catalogPage']??0)===$page);
        }));
        $chapters=[];foreach($all as $product){$p=(int)($product->data['catalogPage']??0);$c=$product->data['category']??'Werbemittel';if($p>0&&!isset($chapters[$c]))$chapters[$c]=['title'=>$c,'page'=>$p];}
        usort($items,fn($a,$b)=>((int)($a->data['catalogPage']??0)<=>(int)($b->data['catalogPage']??0))?:strcmp($a->title,$b->title));
        $requests=$this->preferences('advertising_request');$cartCount=count($cart);$cartQuantity=array_sum(array_column($cart,'quantity'));
        return $this->page('content/advertising.html.twig',compact('all','items','cart','cartCount','cartQuantity','favorites','document','catalog','q','category','categories','favoriteOnly','page','isSearch','chapters','requests'));
    }

    #[Route('/advertising/original',name:'app_advertising_original',methods:['GET'])]
    public function original():Response
    {
        $document=$this->catalogDocument();if(!$document||!($path=$this->materials->filePath($document)))throw $this->createNotFoundException('Originalkatalog nicht verfügbar.');
        $this->audit('catalog.original.download',$document->id);$response=new BinaryFileResponse($path);$response->setContentDisposition('attachment','CHASE-Werbekatalog.pdf');$response->headers->set('Content-Type','application/pdf');return $this->privateFile($response);
    }

    #[Route('/advertising/pages/{page}',name:'app_advertising_page',requirements:['page'=>'\d+'],methods:['GET'])]
    public function pageImage(int $page):Response
    {
        $document=$this->catalogDocument();$catalog=$this->catalogStatus($document);
        if(!$document||!$catalog['previews']||$page<1||$page>$catalog['pages'])throw $this->createNotFoundException('Diese Katalogvorschau ist nicht verfügbar.');
        $base=$this->previewDirectory($document);$file=$base.'/page-'.sprintf('%03d',$page).'.jpg';$real=realpath($file);$realBase=realpath($base);
        if(!$real||!$realBase||is_link($file)||!str_starts_with($real,$realBase.DIRECTORY_SEPARATOR)||!is_file($real))throw $this->createNotFoundException();
        $response=new BinaryFileResponse($real);$response->headers->set('Content-Type','image/jpeg');$response->setContentDisposition('inline','CHASE-Katalog-Seite-'.$page.'.jpg');return $this->privateFile($response);
    }

    #[Route('/advertising/render',name:'app_advertising_render',methods:['POST'])]
    public function renderPreview(Request $request):Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');$this->token($request);$output=new BufferedOutput();
        $status=$this->renderer->run(new ArrayInput(['--tenant'=>$this->user()->tenantId]),$output);
        $this->audit('catalog.preview.prepared');$this->addFlash($status===0?'success':'error',$status===0?'Die lokale Blättervorschau ist bereit.':'Die Blättervorschau konnte nicht erzeugt werden. Dafür müssen der Originalkatalog importiert und die optionalen lokalen Programme pdfinfo und pdftoppm (Poppler) installiert sein. PDF und Produktauswahl bleiben unabhängig davon nutzbar.');
        return $this->redirectToRoute('app_advertising');
    }

    #[Route('/advertising/cart',name:'app_advertising_cart',methods:['GET','POST'])]
    public function cart(Request $request):Response
    {
        $all=$this->products();$cart=$this->cartSelection($all);
        if($request->isMethod('POST')){
            $this->token($request);$raw=$request->request->all('quantities');$valid=[];
            foreach($raw as $id=>$quantity){$this->productFrom($all,(string)$id);if(!$this->validQuantity($quantity,true)){$this->addFlash('error','Bitte ganze Mengen zwischen 0 und 9999 angeben. Ihre Auswahl wurde nicht verändert.');return $this->redirectToRoute('app_advertising_cart');}if((int)$quantity>0)$valid[(string)$id]=(int)$quantity;}
            $this->saveCart($valid);$this->addFlash('success','Ihre Auswahl wurde gespeichert.');return $this->redirectToRoute('app_advertising_cart');
        }
        $cartCount=count($cart);$cartQuantity=array_sum(array_column($cart,'quantity'));$requests=$this->preferences('advertising_request');
        return $this->page('catalog/cart.html.twig',compact('cart','cartCount','cartQuantity','requests'));
    }

    #[Route('/advertising/cart/item/{id}',name:'app_advertising_cart_item',methods:['POST'])]
    public function cartItem(Request $request,string $id):Response
    {
        $this->token($request);$all=$this->products();$this->productFrom($all,$id);$quantity=$request->request->get('quantity','1');$replace=$request->request->getString('mode')==='replace';
        if(!$this->validQuantity($quantity,$replace)){$this->addFlash('error','Bitte eine ganze Menge zwischen '.($replace?'0':'1').' und 9999 angeben.');return $this->redirectToRoute('app_advertising_cart');}
        $selection=[];foreach($this->cartSelection($all) as $entry)$selection[$entry['product']->id]=$entry['quantity'];$total=$replace?(int)$quantity:($selection[$id]??0)+(int)$quantity;
        if($total>9999){$this->addFlash('error','Pro Design sind höchstens 9999 Stück möglich. Die Auswahl bleibt unverändert.');return $this->redirectToRoute('app_advertising_cart');}
        if($total===0)unset($selection[$id]);else $selection[$id]=$total;$this->saveCart($selection);$this->addFlash('success',$total===0?'Design aus Ihrer Auswahl entfernt.':'Das Design liegt jetzt in Ihrer Auswahl.');
        return $request->request->getString('back')==='cart'?$this->redirectToRoute('app_advertising_cart'):$this->catalogReturn($request);
    }

    #[Route('/advertising/cart/clear',name:'app_advertising_cart_clear',methods:['POST'])]
    public function clearCart(Request $request):Response{$this->token($request);$this->saveCart([]);$this->addFlash('success','Ihre Auswahl wurde geleert.');return $this->redirectToRoute('app_advertising_cart');}

    #[Route('/advertising/favorite/{id}',name:'app_advertising_favorite',methods:['POST'])]
    public function favorite(Request $request,string $id):Response
    {
        $this->token($request);$product=$this->productFrom($this->products(),$id);$existing=null;
        foreach($this->preferences('favorite') as $preference)if(($preference->data['targetId']??'')===$id){$existing=$preference;break;}
        if($existing)$this->store->remove($existing);else $this->save(new UserPreference(['title'=>$product->title,'kind'=>'favorite','targetId'=>$id,'targetType'=>'advertising'],$this->user()->id,$this->user()->tenantId));
        return $this->catalogReturn($request);
    }

    #[Route('/advertising/request',name:'app_advertising_request',methods:['POST'])]
    public function advertisingRequest(Request $request):Response
    {
        $this->token($request);$all=$this->products();$items=[];$fromCart=!$request->request->has('quantities');$quantities=$fromCart?[]:$request->request->all('quantities');
        if($fromCart)foreach($this->cartSelection($all) as $entry)$quantities[$entry['product']->id]=$entry['quantity'];
        foreach($quantities as $id=>$raw){$product=$this->productFrom($all,(string)$id);if(!$this->validQuantity($raw,true)){$this->addFlash('error','Bitte geben Sie ganze Mengen zwischen 0 und 9999 an.');return $this->redirectToRoute($fromCart?'app_advertising_cart':'app_advertising');}if((int)$raw>0)$items[]=['id'=>$product->id,'title'=>$product->title,'quantity'=>(int)$raw,'unit'=>$product->data['unit']??'Stück','catalogPage'=>$product->data['catalogPage']??null,'sourceDocumentId'=>$product->data['sourceDocumentId']??null];}
        $note=trim($request->request->getString('note'));if(!$items||mb_strlen($note)>2000){$this->addFlash('error',$items?'Die Notiz darf höchstens 2000 Zeichen enthalten.':'Wählen Sie zuerst mindestens ein Design mit einer Menge aus.');return $this->redirectToRoute($fromCart?'app_advertising_cart':'app_advertising');}
        $user=$this->user();$draft=new UserPreference(['title'=>'Werbemittel-Anfrage vom '.(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Berlin')))->format('d.m.Y H:i'),'kind'=>'advertising_request','items'=>$items,'note'=>$note,'status'=>'Lokaler Entwurf','sent'=>false,'personalizationPreview'=>$request->request->getBoolean('personalize'),'partnerName'=>$user->name,'partnerRegion'=>$user->region],$user->id,$user->tenantId);
        $this->save($draft);$this->audit('advertising.draft.created',$draft->id);$this->addFlash('success','Ihre Angebotsanfrage ist lokal gespeichert. Sie können sie für die Abstimmung herunterladen. Es wurde nichts bestellt oder versendet.');return $this->redirectToRoute('app_advertising',['tab'=>'requests']);
    }

    #[Route('/advertising/request/{id}/download',name:'app_advertising_request_download',methods:['GET'])]
    public function requestDownload(string $id):Response
    {
        $draft=$this->owned(UserPreference::class,$id);if($draft->ownerId!==$this->user()->id||($draft->data['kind']??'')!=='advertising_request')throw $this->createNotFoundException();
        $text="CHASE · LOKALER ANFRAGEENTWURF\nKeine Bestellung · nicht versendet\n\n";
        foreach($draft->data['items']??[] as $item)$text.=$item['quantity'].' × '.$item['title'].' ('.$item['unit'].')'.(!empty($item['catalogPage'])?' · Katalogseite '.$item['catalogPage']:'')."\n";
        $text.="\nPartner: ".($draft->data['partnerName']??'')."\nRegion: ".($draft->data['partnerRegion']??'')."\nNotiz: ".($draft->data['note']??'')."\n\nAlle Preise auf Anfrage. Für Werbemittel sind im Katalog keine Bestellpreise angegeben. Flyeralarm und ein automatischer Angebotsversand sind nicht verbunden. Dieser Export löst keine Bestellung aus.\n";
        return new Response($text,200,['Content-Type'=>'text/plain; charset=UTF-8','Content-Disposition'=>HeaderUtils::makeDisposition('attachment','CHASE-Anfrageentwurf-'.$draft->id.'.txt'),'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }

    #[Route('/advertising/request/{id}/delete',name:'app_advertising_request_delete',methods:['POST'])]
    public function deleteRequest(Request $request,string $id):Response
    {
        $this->token($request);$draft=$this->owned(UserPreference::class,$id);if($draft->ownerId!==$this->user()->id||($draft->data['kind']??'')!=='advertising_request')throw $this->createNotFoundException();$this->store->remove($draft);$this->addFlash('success','Der lokale Anfrageentwurf wurde gelöscht.');return $this->redirectToRoute('app_advertising',['tab'=>'requests']);
    }

    private function products():array{$all=$this->store->visible(AdvertisingProduct::class,$this->user());$real=array_values(array_filter($all,fn($p)=>!empty($p->data['provided'])&&!empty($p->data['sourceDocumentId'])));return $real?:$all;}
    private function productFrom(array $all,string $id):Record{foreach($all as $product)if($product->id===$id)return $product;throw $this->createNotFoundException('Dieses Design ist nicht verfügbar.');}
    private function preferences(string $kind):array{return array_values(array_filter($this->store->visible(UserPreference::class,$this->user()),fn($p)=>$p->ownerId===$this->user()->id&&($p->data['kind']??'')===$kind));}
    private function preferenceTargets(string $kind):array{return array_values(array_unique(array_filter(array_map(fn($p)=>$p->data['targetId']??null,$this->preferences($kind)))));}
    private function cartSelection(array $products):array{$record=$this->preferences('advertising_cart')[0]??null;$selection=$record?->data['items']??[];$result=[];foreach($products as $product){$qty=$selection[$product->id]??0;if($this->validQuantity($qty,false))$result[]=['product'=>$product,'quantity'=>(int)$qty];}return $result;}
    private function saveCart(array $selection):void{$record=$this->preferences('advertising_cart')[0]??new UserPreference(['title'=>'Meine Werbemittelauswahl','kind'=>'advertising_cart','items'=>[]],$this->user()->id,$this->user()->tenantId);$record->data['items']=$selection;$this->save($record);}
    private function validQuantity(mixed $raw,bool $zero):bool{return is_scalar($raw)&&preg_match('/^\d{1,4}$/D',(string)$raw)&&($zero?(int)$raw>=0:(int)$raw>0)&&(int)$raw<=9999;}
    private function catalogReturn(Request $request):Response{$parameters=['page'=>max(1,min(100,$request->request->getInt('page',1)))];foreach(['q','category'] as $field){$value=mb_substr(trim($request->request->getString($field)),0,200);if($value!=='')$parameters[$field]=$value;}if($request->request->getBoolean('favorites'))$parameters['favorites']=1;return $this->redirectToRoute('app_advertising',$parameters);}
    private function catalogDocument():?Record{foreach($this->store->visible(Document::class,$this->user()) as $record)if(($record->data['catalogOriginal']??false)&&($record->data['provided']??false))return $record;return null;}
    private function previewDirectory(Record $document):string{return $this->projectDir.'/var/materials/catalog/'.substr(hash('sha256',$this->user()->tenantId),0,16).'/'.$document->data['sourceHash'];}
    private function catalogStatus(?Record $document):array
    {
        $status=['available'=>false,'previews'=>false,'pages'=>14];if(!$document||!$this->materials->filePath($document))return $status;$status['available']=true;$base=$this->previewDirectory($document);$manifest=$base.'/manifest.json';if(!is_file($manifest)||is_link($manifest))return $status;
        $data=json_decode((string)file_get_contents($manifest),true);if(!is_array($data)||($data['sourceHash']??'')!==($document->data['sourceHash']??null)||($data['documentId']??'')!==$document->id||($data['pages']??0)<1||($data['pages']??0)>100)return $status;
        $status['pages']=(int)$data['pages'];for($p=1;$p<=$status['pages'];$p++){if(!is_file($base.'/page-'.sprintf('%03d',$p).'.jpg'))return $status;}$status['previews']=true;return $status;
    }
    private function privateFile(BinaryFileResponse $response):BinaryFileResponse{$response->setPrivate();$response->headers->set('Cache-Control','private, no-store');$response->headers->set('X-Content-Type-Options','nosniff');$response->headers->set('X-Robots-Tag','noindex, nofollow');return $response;}
}
