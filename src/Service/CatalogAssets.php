<?php
namespace App\Service;
use App\Entity\Record;
/** Metadata for authentic product crops; every image is derived locally from the supplied PDF. */
final class CatalogAssets {
 public function __construct(private string $projectDir){}
 public function definitions():array{$rows=json_decode((string)file_get_contents($this->projectDir.'/data/catalog-products.json'),true,512,JSON_THROW_ON_ERROR);return $rows['products']??[];}
 public function details(Record $product):array{$row=[];foreach($this->definitions() as $candidate)if(($candidate['id']??'')===($product->data['id']??null)){$row=$candidate;break;}return array_merge($row,$product->data);}
 public function directory(string $tenant,string $hash):?string{if(!preg_match('/^[a-f0-9]{64}$/D',$hash))return null;return $this->projectDir.'/var/materials/catalog/'.substr(hash('sha256',$tenant),0,16).'/'.$hash;}
 public function filename(array $row):?string{$key=$row['id']??'';$crop=$row['previewCrop']??[];if(!is_string($key)||!preg_match('/^page-\d{2}-design-\d{2}$/D',$key)||count($crop)!==4)return null;return 'product-'.$key.'-'.substr(hash('sha256',json_encode($crop,JSON_THROW_ON_ERROR)),0,8).'.jpg';}
 public function renderedPath(Record $product):?string{$row=$this->details($product);$base=$this->directory($product->tenantId,(string)($row['sourceHash']??''));$name=$this->filename($row);if(!$base||!$name)return null;$path=$base.'/'.$name;$real=realpath($path);$realBase=realpath($base);return $real&&$realBase&&!is_link($path)&&str_starts_with($real,$realBase.DIRECTORY_SEPARATOR)&&is_file($real)?$real:null;}
}
