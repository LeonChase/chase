<?php
namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
class SecurityController extends AbstractController {
 #[Route('/login',name:'app_login',methods:['GET','POST'])]
 public function login(AuthenticationUtils $auth):Response{if($this->getUser())return $this->redirect('/');return $this->render('security/login.html.twig',['error'=>$auth->getLastAuthenticationError(),'last_username'=>$auth->getLastUsername()]);}
 #[Route('/logout',name:'app_logout',methods:['POST'])]
 public function logout():never{throw new \LogicException('Symfony Security handles logout.');}
}
