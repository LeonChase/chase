<?php
namespace App\Service;
use App\Entity\Record;
/** Region-level demo markers, never claimed to be real office addresses. */
final class PartnerMap
{
 public function location(Record $profile):?array{
  $lat=$profile->data['latitude']??null;$lon=$profile->data['longitude']??null;$approximate=false;
  if(($lat===null||$lon===null)&&!empty($profile->data['isDemo'])){
   $region=mb_strtolower((string)($profile->data['region']??''));
   foreach(['hameln'=>[52.10,9.36],'hildesheim'=>[52.15,9.95],'kassel'=>[51.31,9.49],'passau'=>[48.57,13.43]] as $name=>$coords)if(str_contains($region,$name)){[$lat,$lon]=$coords;$approximate=true;break;}
  }
  if(!is_numeric($lat)||!is_numeric($lon)||!is_finite((float)$lat)||!is_finite((float)$lon)||$lat<47||$lat>55.5||$lon<5.5||$lon>15.5)return null;
  return ['x'=>round(40+((float)$lon-5.5)*40,2),'y'=>round(30+(55.4-(float)$lat)*62,2),'approximate'=>$approximate];
 }
}
