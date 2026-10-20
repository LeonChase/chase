<?php
namespace App\Integration;
/** Future trust boundary. Never copy sessions or credentials from the live CHASE site. */
interface AuthBridge {
 public function isConnected():bool;
 public function strategy():string;
}
