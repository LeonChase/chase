<?php
namespace App\Controller;
use App\Entity\UserPreference;
use App\Service\ToolLinks;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
final class LinksController extends PortalController
{
 #[Route('/links',name:'tool_links',methods:['GET'])]
 public function index(Request $request,ToolLinks $directory):Response{
  $all=$directory->all();$favorites=[];foreach($this->store->visible(UserPreference::class,$this->user()) as $p)if(($p->data['kind']??'')==='tool_link_favorite')$favorites[]=$p->data['targetId'];
  $q=mb_substr(trim($request->query->getString('q')),0,200);$category=$request->query->getString('category');$onlyFavorites=$request->query->getBoolean('favorites');
  $links=array_values(array_filter($all,fn($link)=>($category===''||$link['category']===$category)&&(!$onlyFavorites||in_array($link['id'],$favorites,true))&&($q===''||str_contains(mb_strtolower($link['title'].' '.$link['description'].' '.$link['category']),mb_strtolower($q)))));
  $categories=array_values(array_unique(array_column($all,'category')));
  return $this->page('links/index.html.twig',compact('links','favorites','q','category','onlyFavorites','categories'));
 }
 #[Route('/links/{id}/favorite',name:'tool_link_favorite',methods:['POST'])]
 public function favorite(string $id,Request $request,ToolLinks $directory):Response{
  $this->token($request);if(!$directory->find($id))throw $this->createNotFoundException();$found=null;foreach($this->store->visible(UserPreference::class,$this->user()) as $p)if(($p->data['kind']??'')==='tool_link_favorite'&&($p->data['targetId']??'')===$id){$found=$p;break;}
  if($found)$this->store->remove($found);else $this->save(new UserPreference(['kind'=>'tool_link_favorite','targetId'=>$id,'title'=>$directory->find($id)['title']],$this->user()->id,$this->user()->tenantId));return $this->redirect('/links');
 }
}
