<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_credit_ledger')]
#[ORM\Index(name:'portal_credit_ledger_scope',columns:['tenant_id','owner_id'])]
class CreditLedger extends Record {}
