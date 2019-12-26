<?php 
$id=$argv[1];
$host='localhost';
$db='pathways';
$dsn= "mysql:host=$host;dbname=$db";
$pdo = new PDO($dsn, 'root', 'muhammad');
$st = $pdo->query('select * from user_pathway_status where id='.$id)->fetchAll();
// print_r($st);
$pw=$st[0]['pathway'];
$data['source']='obServer';
$data['platform']='ob';
$data['user_id']=$st[0]['user_id'];
$data['organization_id']=$st[0]['practice_id'];
//$data['condition_key']=strtolower(file_get_contents('http://'.$_SERVER['SERVER_NAME'].'pathways/index.php/api/pw/getPathwayName/'.$pw));
if($pw==25)
{
    $data['condition_key']='bloodTestMale';
}
if($pw==20)
{
    $data['condition_key']='sti-male';
}
if($pw==25 && strtolower($params['gender'])=='male' )
{
    $data['condition_key']='bloodTestMale';
}
if($pw==25 && strtolower($params['gender'])=='female' )
{
    $data['condition_key']='bloodTestFemale';
}
if($pw==20 && strtolower($params['gender'])=='male')
{
    $data['condition_key']='sti-male';
}
if($pw==20 && strtolower($params['gender'])=='female')
{
    $data['condition_key']='sti-female';
}
if($pw==22)
{
    $data['condition_key']='chase-referrer';
}
if($pw==21)
{
    $data['condition_key']='sick-note';
}
if($pw==24)
{
    $data['condition_key']='order-medication';
}
if($pw==26)
{
    $data['condition_key']='general-advice';
}


$st=$pdo->query('select * from step_answers where user_id='.$data['user_id'].' and pathway='.$pw)->fetchAll(); 
$data['condition_schema']=array();
// print_r($params);exit;
$count=count($st);
$i=0;

foreach($st as $row)
{
    //$step=$this->getStepByNumber($row['step'], $params['pathway']);
    // get the step number
    $st2=$pdo->query('select * from steps where pathway='.$pw.' and number='.$row['step'])->fetchAll();
    $step=$st2[0];
    // print_r($step);
    if($step['type']=='question' || $step['type']=='info'|| $step['type']=='alert'  )
    {
        //$q=$step['id'];
        // get question of the step
        $st4=$pdo->query('select * from step_questions where step='.$step['id'])->fetchAll();
        $qu=$pdo->query('select * from questions where id='.$st4[0]['question'])->fetchAll();
        $q=$qu[0];
        // print_r($q);exit;
        if($q['ans_model']==16)
        {
            $st5=$pdo->query('select * from step_answers where pathway='.$pw. 'and user_id='.$data['user_id']
            .' and step='.$step['number'])->fetchAll();

            $d=array();
            $d[0]['value']=$st5[0]['value'];
            $dr=array(
                'question'  => $q['statement'],
                'selected_choice'    => $d[0]['value']
            );
        }
        else
        {
            if($q)
            {
                $dr=array(
                    'question'  => $q['statement'],
                    'selected_choice'    => getAnswerResult($q[0],$row)
                );
            }
            else
            {
                $dr=array(
                    'type'      => $step['type'],
                    'question'  => array(),
                    'selected_choice'    => array()
                );
            }                    
            
        }
        array_push($data['condition_schema'], $dr);
            
    }
}

// print_r($data);


function getAnswerResult($q, $row)
{
    // echo $q;exit;
    $host='localhost';
    $db='pathways';
    $dsn= "mysql:host=$host;dbname=$db";
    $pdo = new PDO($dsn, 'root', 'muhammad');
    $t=$pdo->query('select * from questions where id='.$q)->fetchAll();
    if($t[0]['type']=='Question')
    {
        $d=$pdo->query('select * from answer_models inner join questions on questions.ans_model=answer_models.id 
        where questions.id='.$q)->fetchAll();
        // print_r($d);
        // echo 'Q=:';print_r($d[0]);echo'<br>';
        // if answer model only has one text field
        // return the value sent in the row
        if($d[0]['ans_model']==27 || $row['field_name']=='other')
        {
            // print_r($row);
            $caption=array();
            $caption['value']=$row['value'];
            return $caption;
        }
        $arr=array();
        
        if(strpos($row['value'], ','))
        {
            $arr=explode(',', $row['value']);            
        }
        if(count($arr)>0)
        {
            $caption=array();
            $caption[0]['value']='';
            for($i=0;$i<count($arr);$i++)
            {
                $st6=$pdo->query('select caption from ans_form where question='.$q.' and value='.$arr[$i])->fetchAll();
                // print_r($st[0]);
                if(count($st)>0)
                {
                    $caption[0]['value'].=($i+1).': '.$st6[0]['caption'].' <br> ';
                }
                
            }
            // print_r($caption);
            return $caption;
        }
        else
        {
            $caption=array();
            $st6=$pdo->query('select caption from ans_form where question='.$q.' and value='.$row['value'])->fetchAll();
            // echo $pdo->last_query();
            if(count($st6)>0)
            {
                $caption[0]['value']=$st6[0]['caption'];
                return $caption;
            }
            else
            {
                return array();
            }
        }
        
    }
    
    
} 
// $data['condition_schema']=$this->Admin_model->pathway_review_for_BS($params);
$endpoint='v3/dr-iq/onboarding/pathway-save';
$url = 'https://qa-driq-server.attech-ltd.com/'.$endpoint;
//$url = 'https://stag-server.attech-ltd.com/'.$endpoint;
$myvars = http_build_query($data, '', '&');
// $this->Admin_model->changeIsSubmittedStatus($params, 'yes');
$ch = curl_init( $url );
curl_setopt( $ch, CURLOPT_POST, 1);
curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt( $ch, CURLOPT_HEADER, 0);
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

curl_exec( $ch );


?>