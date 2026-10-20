<?php
namespace App\Service;

use App\Entity\Task;

/** Local RFC 5545 file exchange. This service never contacts calendar providers. */
final class CalendarExport
{
    /** @param Task[] $tasks */
    public function export(array $tasks): string
    {
        $lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//CHASE//Lokaler Aufgabenplaner//DE','CALSCALE:GREGORIAN','METHOD:PUBLISH'];
        foreach($tasks as $task){
            $due=$this->date((string)($task->data['dueDate']??''));
            if(!$due)continue;
            $lines[]='BEGIN:VEVENT';
            $lines[]='UID:'.$this->escape($task->id.'@chase.local');
            $lines[]='DTSTAMP:'.$task->updatedAt->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
            $lines[]='DTSTART;VALUE=DATE:'.$due->format('Ymd');
            $lines[]='DTEND;VALUE=DATE:'.$due->modify('+1 day')->format('Ymd');
            $lines[]='SUMMARY:'.$this->escape($task->title);
            $lines[]='DESCRIPTION:'.$this->escape((string)($task->data['notes']??''));
            $lines[]='X-CHASE-TASK-STATUS:'.$this->escape((string)($task->data['status']??'Offen'));
            $lines[]='X-CHASE-PRIORITY:'.$this->escape((string)($task->data['priority']??'Mittel'));
            $lines[]='TRANSP:TRANSPARENT';
            $lines[]='END:VEVENT';
        }
        $lines[]='END:VCALENDAR';
        return implode("\r\n",array_map($this->fold(...),$lines))."\r\n";
    }

    /** @return array{events:array,skipped:int} */
    public function import(string $content): array
    {
        if(strlen($content)>1048576||!mb_check_encoding($content,'UTF-8')||str_contains($content,"\0"))throw new \InvalidArgumentException('Bitte eine UTF-8-Kalenderdatei bis 1 MB verwenden.');
        $content=preg_replace('/\r?\n[ \t]/','',$content)??$content;
        $lines=preg_split('/\r\n|\n|\r/',$content)?:[];
        if(count($lines)>12000||!in_array('BEGIN:VCALENDAR',$lines,true)||!in_array('END:VCALENDAR',$lines,true))throw new \InvalidArgumentException('Die Datei ist kein unterstützter iCalendar-Kalender.');
        $events=[];$event=null;$skipped=0;$depth=0;$seen=0;
        foreach($lines as $line){
            if($line==='BEGIN:VEVENT'){
                if($event!==null||++$seen>500)throw new \InvalidArgumentException('Bitte höchstens 500 einfache Termine pro Datei importieren.');
                $event=[];$depth=0;continue;
            }
            if($event===null)continue;
            if($line==='END:VEVENT'){
                $parsed=$this->event($event);
                if($parsed)$events[]=$parsed;else ++$skipped;
                $event=null;continue;
            }
            if(str_starts_with($line,'BEGIN:')){++$depth;continue;}
            if(str_starts_with($line,'END:')){--$depth;continue;}
            if($depth>0)continue; // Alarms and attachments are never imported or executed.
            if(!str_contains($line,':'))continue;
            [$header,$value]=explode(':',$line,2);$parts=explode(';',$header);$name=strtoupper(array_shift($parts));
            if(in_array($name,['UID','SUMMARY','DESCRIPTION','DTSTART','RRULE','RECURRENCE-ID','STATUS','X-CHASE-TASK-STATUS','X-CHASE-PRIORITY'],true))$event[$name]=['value'=>$value,'params'=>$parts];
        }
        if($event!==null)throw new \InvalidArgumentException('Der Kalender enthält einen unvollständigen Termin.');
        return ['events'=>$events,'skipped'=>$skipped];
    }

    private function event(array $event): ?array
    {
        if(isset($event['RRULE'])||isset($event['RECURRENCE-ID'])||($event['STATUS']['value']??'')==='CANCELLED')return null;
        $title=trim($this->unescape($event['SUMMARY']['value']??''));$start=$event['DTSTART']['value']??'';
        $date=null;
        if(preg_match('/^\d{8}$/',$start))$date=$this->date(substr($start,0,4).'-'.substr($start,4,2).'-'.substr($start,6,2));
        elseif(preg_match('/^\d{8}T\d{6}Z?$/',$start)){
            $timezone='Europe/Berlin';
            foreach($event['DTSTART']['params'] as $param)if(str_starts_with(strtoupper($param),'TZID='))$timezone=trim(substr($param,5),'"');
            try{
                $utc=str_ends_with($start,'Z');$format=$utc?'!Ymd\THis\Z':'!Ymd\THis';
                $date=\DateTimeImmutable::createFromFormat($format,$start,new \DateTimeZone($utc?'UTC':$timezone));
                if(!$date||$date->format($utc?'Ymd\THis\Z':'Ymd\THis')!==$start)$date=null;
                else $date=$date->setTimezone(new \DateTimeZone('Europe/Berlin'));
            }catch(\Exception){$date=null;}
        }
        $notes=$this->unescape($event['DESCRIPTION']['value']??'');
        if(!$date||$title===''||mb_strlen($title)>250||mb_strlen($notes)>5000)return null;
        $status=$this->unescape($event['X-CHASE-TASK-STATUS']['value']??'Offen');
        $priority=$this->unescape($event['X-CHASE-PRIORITY']['value']??'Mittel');
        $uid=$this->unescape($event['UID']['value']??'');
        return ['title'=>$title,'dueDate'=>$date->format('Y-m-d'),'notes'=>$notes,'status'=>in_array($status,['Offen','In Arbeit','Erledigt'],true)?$status:'Offen','priority'=>in_array($priority,['Niedrig','Mittel','Hoch'],true)?$priority:'Mittel','calendarUid'=>mb_substr($uid!==''?$uid:hash('sha256',$title.'|'.$date->format('Y-m-d').'|'.$notes),0,512)];
    }

    private function date(string $value): ?\DateTimeImmutable
    {
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value,new \DateTimeZone('Europe/Berlin'));
        return $date&&$date->format('Y-m-d')===$value?$date:null;
    }
    private function escape(string $value): string
    {
        return str_replace(["\\","\r\n","\r","\n",';',','],['\\\\','\\n','\\n','\\n','\\;','\\,'],$value);
    }
    private function unescape(string $value): string
    {
        return preg_replace_callback('/\\\\([nN,;\\\\])/',fn($m)=>in_array($m[1],['n','N'],true)?"\n":$m[1],$value)??$value;
    }
    private function fold(string $line): string
    {
        $parts=[];$limit=75;
        while(strlen($line)>$limit){$part=mb_strcut($line,0,$limit,'UTF-8');$parts[]=$part;$line=substr($line,strlen($part));$limit=74;}
        $parts[]=$line;
        return implode("\r\n ",$parts);
    }
}
