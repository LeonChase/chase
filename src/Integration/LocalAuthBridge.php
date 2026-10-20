<?php
namespace App\Integration;
final class LocalAuthBridge implements AuthBridge {
 public function isConnected():bool{return false;}
 public function strategy():string{return 'Lokale Symfony-Session; SSO nicht verbunden. Künftig gemeinsame geprüfte Userbasis oder OIDC.';}
}
