<?php
namespace App\EventSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
class ResponseHeaders implements EventSubscriberInterface {
 public static function getSubscribedEvents():array{return [KernelEvents::RESPONSE=>'headers'];}
 public function headers(ResponseEvent $event):void {
  if(!$event->isMainRequest())return;
  $h=$event->getResponse()->headers;
  $h->set('X-Content-Type-Options','nosniff');$h->set('Referrer-Policy','same-origin');$h->set('X-Frame-Options','DENY');
  $h->set('Content-Security-Policy',"default-src 'self'; img-src 'self' blob: data:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self'; connect-src 'self'; object-src 'self' blob:; frame-src 'self' blob:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
  $h->set('Cache-Control','private, no-store');
 }
}
