<?php
namespace App\Security;
use App\Entity\{Record,User,Document,AcademyArticle,AdvertisingProduct,NewsPost,PartnerProfile};
use App\Service\PortalStore;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
class RecordVoter extends Voter {
 public function __construct(private PortalStore $store){}
 protected function supports(string $attribute,mixed $subject):bool{return in_array($attribute,['VIEW','EDIT','DELETE','DOWNLOAD'],true)&&$subject instanceof Record;}
 protected function voteOnAttribute(string $attribute,mixed $subject,TokenInterface $token):bool{
  $user=$token->getUser();if(!$user instanceof User||!$this->store->canAccess($subject,$user))return false;
  $shared=$subject instanceof Document||$subject instanceof AcademyArticle||$subject instanceof AdvertisingProduct||$subject instanceof NewsPost||$subject instanceof PartnerProfile;
  if($shared&&in_array($attribute,['EDIT','DELETE'],true))return (bool)array_intersect($user->roles,['ROLE_ADMIN','ROLE_SUPER_ADMIN']);
  return true;
 }
}
