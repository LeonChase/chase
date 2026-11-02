<?php
namespace App\Tests\Unit;
use App\Entity\PartnerProfile;
use App\Service\PartnerMap;
use PHPUnit\Framework\TestCase;
final class PartnerMapTest extends TestCase{
 public function testOnlyDemoRegionsHaveApproximateFallbackAndExplicitCoordinatesAreBounded():void{
  $map=new PartnerMap();self::assertNull($map->location(new PartnerProfile(['region'=>'Hameln-Pyrmont'])));$demo=$map->location(new PartnerProfile(['region'=>'Hameln-Pyrmont','isDemo'=>true]));self::assertNotNull($demo);self::assertTrue($demo['approximate']);
  $explicit=$map->location(new PartnerProfile(['latitude'=>52.1,'longitude'=>9.36]));self::assertFalse($explicit['approximate']);self::assertSame($demo['x'],$explicit['x']);
  foreach([['latitude'=>'bad','longitude'=>9],['latitude'=>90,'longitude'=>9],['latitude'=>51,'longitude'=>INF]] as $data)self::assertNull($map->location(new PartnerProfile($data)));
 }
}
