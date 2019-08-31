<?php
/**
 * Created by PhpStorm.
 * User: sun rise
 * Date: 8/2/2016
 * Time: 3:48 PM
 */
class Admin_model extends CI_Model {
    function __construct()
    {
        parent::__construct();
    }
    public function getFirstPathwayQuestion($id, $user_id=0, $age=0)
    {
        if($user_id==0)
        {
            $st=$this->db->select('*')->from('pathflow')->where('pathway',$id)->where('back',0)->get()->result_array();
            // echo '<pre>';print_r($this->db->last_query());exit;
            if(!count($st)>0)
            {
                return false;
            }
            $data=$st[0];
        }
        else
        {
            $st=$this->db->select('*')->from('user_pathway_status')->where('pathway',$id)->where('user_id',$user_id)->get()->result_array();
            // echo '<pre>';print_r($this->db->last_query());exit;
            if(count($st)>0)
            {
                if($st[0]['percent']==100)
                {
                    $st=$this->db->select('*')->from('pathflow')->where('pathway',$id)->where('back',0)->get()->result_array();
                    // echo '<pre>';print_r($this->db->last_query());exit;
                    if(!count($st)>0)
                    {
                        return false;
                    }
                    $data=$st[0];
                }
                else
                {
                    $data=array();
                    $step=$this->getStepByNumberPathway($st[0]['current_step'],$id);
                    if($step['type']!='question' && $step['type']!='info')
                    {
                        do {
                            $path=$this->getPathFlowByStep($step['number'], $id);
                            // print_r($path);exit;
                            $step=$this->getStepByNumber($path['back'], $id);
                            $path=$this->getPathFlowByStep($step['number'], $id);

                        }while($step['type']!='question');
                        
                        $params['step']=$path['step'];
                        $params['next']=$path['next'];
                        $data['step']=$step['number'];
                        $data['back']=$path['back'];
                        $data['next']=$path['next'];
                        $data['pathway']=$id;
                        $data['percent']=(int)$st[0]['percent'];
                    }
                    else
                    {
                        $path=$this->getPathFlowByStep($step['number'], $id);
                        $data['step']=$step['number'];
                        $data['back']=$path['back'];
                        $data['next']=$path['next'];
                        $data['pathway']=$id;
                        $data['percent']=(int)$st[0]['percent'];
                    }
                    
                    
                }
            }
            else
            {
                $st=$this->db->select('*')->from('pathflow')->where('pathway',$id)->where('back',0)->get()->result_array();
                // echo '<pre>';print_r($this->db->last_query());exit;
                if(!count($st)>0)
                {
                    return false;
                }
                $data=$st[0];
            }    
        }  
        $step=$this->getStepByNumberPathway($data['step'],$id);
        $data['step']=$step['number'];
        
        if($data['pathway']==3 && $data['step']==3)
        {
            $data['step']="1";
            $step=$this->getStepByNumberPathway($data['step'],$id);
            $data['percent']=0;
        }
        if($id==5)
        {
            if($step['type']=='age')
            {
                $st=$this->db->select('*')->from('step_age')
                            ->where('step',$step['id'])
                            ->get()->result_array();
                            // print_r($age);exit;
                if($age > $st[0]['value'])
                {
                    $step=$step=$this->getStepByNumberPathway($st[0]['if_next_step'],$id);
                    $data['step']=$step['number'];
                    $t=$this->db->select('*')->from('pathflow')->where('pathway',$id)->where('step',$step['number'])->get()->result_array();
                    $data['back']=$t[0]['back'];
                    $data['next']=$t[0]['next'];
                }
                else
                {
                    $step=$step=$this->getStepByNumberPathway($st[0]['else_next_step'],$id);
                    $data['step']=$step['number'];
                    $t=$this->db->select('*')->from('pathflow')->where('pathway',$id)->where('step',$step['number'])->get()->result_array();
                    $data['back']=$t[0]['back'];
                    $data['next']=$t[0]['next'];
                }

            }
            // echo '<pre>';print_r($data);exit;
        }
        // print_r($data);exit;
        // echo '<pre>';print_r($step);exit;
        $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
        //echo '<pre>';print_r($this->db->last_query());exit;
        $data['question']=$st[0];
        if(!isset($data['percent']))
        {            
            $data['percent']=0;
        }
        return $data;
    }

    public function getNextPathwayQuestion($params)
    {        
        // echo '<pre>1';print_r($params); exit;
        
        $st=$this->db->select('*')->from('pathflow')
                ->where('pathway',$params['pathway'])
                ->where('step',$params['next'])
                ->get()->result_array();
        // echo '<pre>';print_r($this->db->last_query()); exit;
        $data=$st[0];
        $step=$this->getStepByNumber($data['step'], $params['pathway']);
        // echo '<pre>1';print_r($data); print_r($step);exit;
        $result=0;
        
        if($step['type']=='question' || $step['type']=='info')
        {
           // echo "<script>console.log('52. next step is question')</script>";
            $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.($step['id']))->result_array();
            $data['question']=$st[0];
            $steps=count($this->db->select('*')->from('steps')
                        ->where('pathway',$data['pathway'])
                        ->get()->result_array());
            if($data['next']==0)
            {   
                $data['percent']=100;
            }
            
            //echo '<pre>';print_r($steps);
            // echo '111 go';
            $data['percent']=($data['step']/$steps)*100;
            $data['user_id']=$params['user_id'];
            
            $this->updateStats($data);
            $st=$this->getStats($data);
            $data['percent']=(int)$st['percent'];
            return $data;
        }
        else
        {
            // echo "<script>console.log('70. next step ".$step['number']." is not question')</script>";
            $data=$this->checkNextStep($step,$params);
            $steps=count($this->db->select('*')->from('steps')
                        ->where('pathway',$params['pathway'])
                        ->get()->result_array());

            // print_r($data);
            // echo '125 go';
            $data['percent']=($data['step']/$steps)*100;

            if($data['step']==$steps)
            {
                // echo '128 go';
                $data['next']="0";
                $data['percent']=100;
            }
            if($data['next']==0)
            {
                $data['percent']=100;
            }
            $data['user_id']=$params['user_id'];
            $data['pathway']=$params['pathway'];
            
            $this->updateStats($data);
            return $data;
        }
        
        
    }

    public function getNextStep($step, $params){

        $next=$this->db->query('select * from pathflow where step='.$step['number'].' and pathway='.$params['pathway'])->result_array();
        // echo '<pre>';print_r($next);exit;
        // print_r($this->getStepByNumber($next[0]['next'], $params['pathway']));exit;
        return $this->getStepByNumber($next[0]['next'], $params['pathway']);
    }
    public function checkNextStep($step,$params)
    {
        // echo '<pre>';print_r($step);exit;
        
        $data=$params;
        // echo '<pre>';print_r($params);exit;
        $result=0;
        if($step['type']=='calculation')
        {
            //echo 'In calculation';exit;
            // echo "<script>console.log('223 Step ".$step['number']." is calculation')</script>";
            $st=$this->db->query('select * from step_calculation where step='.$step['id'])->result_array();
            $stepCalcData=$st[0];
            // print_r($stepCalcData);exit;
            if($params['pathway']==5 && $step['number']==11)
            {
                $st=$this->db->select('*')
                        ->from('step_answers')
                        ->where('step = '.$stepCalcData['from_step'])
                        ->where('user_id',$params['user_id'])
                        ->where('pathway', $params['pathway'])
                        ->get()
                        ->result_array();
                $res1=$st[0]['value'];
                $st=$this->db->select('*')
                        ->from('step_answers')
                        ->where('step = '.$stepCalcData['to_step'])
                        ->where('user_id',$params['user_id'])
                        ->where('pathway', $params['pathway'])
                        ->get()
                        ->result_array();
                $res2=$st[0]['value'];
                $result=$res1*$res2;
            }
            else
            {
                $st=$this->db->select('*')
                        ->from('step_answers')
                        ->where('step BETWEEN '.$stepCalcData['from_step'].' and '.$stepCalcData['to_step'].'')
                        ->where('user_id',$params['user_id'])
                        ->where('pathway', $params['pathway'])
                        ->get()
                        ->result_array();
                // echo '<pre>';print_r($st);exit;
                // $st=$this->db->query('select * from step_answers where step BETWEEN '.$stepCalcData['from_step'].' and '.$stepCalcData['to_step'].'')->result_array();
                
                if(count($st)>0)
                {
                    for($i=0;$i<count($st);$i++)
                    {
                        if($params['pathway']==2 )
                        {
                            if($st[$i]['step']!=7)
                            {
                                $result+=$st[$i]['value'];
                            }                        
                        }
                        else
                        {
                            $result+=$st[$i]['value'];
                        }
                        
                    }
                }
            }
            // echo '<pre>';print_r($stepCalcData);exit;
            
            // echo "<script>console.log('254 saving result ".$result." for step ".$step['number']."')</script>";
            $item=array(
                'pathway' => $params['pathway'],
                'step'      => $step['number'],
                'user_id'   => $params['user_id'],
                'value'     => $result
            );
            $st=$this->db->select('*')
                        ->from('step_answers')
                        ->where('step',$step['number'])
                        ->where('user_id',$params['user_id'])
                        ->where('pathway', $params['pathway'])
                        ->get()
                        ->result_array();
            // print_r($this->db->last_query());exit;
            if(count($st)>0)
            {
                $this->db->where('step',$item['step'])->where('user_id',$params['user_id'])
                        ->where('pathway', $params['pathway'])->update('step_answers',$item);
            }
            else
            {
                $this->db->insert('step_answers',$item);
            }
            // calculate percent
            $d=count($this->db->select('*')->from('steps')->where('pathway',$params['pathway'])
                        ->get()->result_array());
            $percent=round(($step['number']/$d)*100);
            // Save Current Step and save percent in pathway status
            $item=array(
                    'user_id'   =>  $params['user_id'],
                    'pathway'   =>  $params['pathway'],
                    'current_step'  =>  $step['number'],
                    'percent'   =>  $percent
            );
            $st=$this->db->select('*')->from('user_pathway_status')
                        ->where('user_id', $params['user_id'])
                        ->where('pathway', $params['pathway'])
                        ->get()->result_array();
            if(count($st)>0)
            {
                $this->db->where('user_id', $params['user_id'])
                            ->where('pathway', $params['pathway'])
                            ->update('user_pathway_status', $item);
            }
            else
            {
                $this->db->insert('user_pathway_status', $item);
            }
            // get next step
            // print_r($step);exit;
            $step=$this->getNextStep($step,$params);
            // print_r($step);exit;
            $next=$this->db->query('select * from pathflow where step='.$step['number'].' and pathway='.$params['pathway'])->result_array();
            $data['step']=$step['number'];
            $data['back']=$next[0]['back'];
            $data['next']=$next[0]['next'];
            if($step['type']=='question' || $step['type']=='info')
            {
                // echo "<script>console.log('313 next step is question')</script>";
                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$data['step'])->result_array();
                $data['question']=$st[0];
                return $data;
            }
            else
            {

                if($step['type']=='condition')
                {
                    // echo "<script>console.log('323 step ".$step['number']." is condition')</script>";
                    $result=0;
                    $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                    $condition=$st[0];
                    // echo '<pre>';print_r($condition);exit;
                    $d=array();
                    $d['step']=$condition['step_result'];
                    $d['pathway']=$params['pathway'];
                    $d['user_id']=$params['user_id'];

                    $result=$this->getStepAnswer($d);
                    // echo '271 <pre>';print_r($result);exit;
                    //print_r($step['id'].'-'.$params['pathway']); exit;
                    switch($condition['operator'])
                    {
                        case '>':
                            if(isset($result[0]))
                            {
                                if($result[0]['value'] > $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            }
                            else
                            {
                                if($result['value'] > $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            } 
                            
                        break;
                        case '<':
                            if(isset($result[0]))
                            {
                                if($result[0]['value'] < $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            }
                            else
                            {
                                if($result['value'] < $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            } 
                        break;
                        case '==':
                        // echo 'result '.$result['value'];exit;
                            if(isset($result[0]))
                            {
                                if($result[0]['value'] == $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            }
                            else
                            {
                                if($result['value'] == $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            } 
                        break;
                        case '<>':                   
                            if(isset($result[0]))
                             { 
                                if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                { //
                                    // echo '502 go';exit;
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    // print_r($path);
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                }
                            }
                            else
                             { //
                                if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                { //
                                    // echo '502 go';exit;
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    // print_r($path);
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                }
                            }
                        break;
                    }
                    //echo '<pre>';print_r($step);print_r($data);exit;
                    $step=$this->getStepByNumber($data['step'], $params['pathway']);
                    //$step=$this->getNextStep($step,$params);
                    //echo '<pre>';print_r($step);print_r($data);exit;

                    if($step['type']=='question' || $step['type']=='info')
                    {
                        // echo "<script>console.log('518 step ".$step['id']." is question')</script>";
                        $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                        $data['question']=$st[0];
                        return $data;
                    }
                    else
                    {   
                        // echo "<script>console.log('525 next step ".$step['number']." not question')</script>";
                        if($step['type']=='condition')
                        {
                            // echo "<script>console.log('404 step ".$step['number']." is condition')</script>";
                            $result=0;
                            $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                            $condition=$st[0];
                            // echo '<pre>';print_r($condition);exit;
                            $d['step']=$condition['step_result'];
                            $d['pathway']=$params['pathway'];
                            $d['user_id']=$params['user_id'];

                            $result=$this->getStepAnswer($d);
                            // echo '271 <pre>';print_r($result);exit;
                            //print_r($step['id'].'-'.$params['pathway']); exit;
                            // echo "<script>console.log('540 result is".$result['value']."')</script>";
                            switch($condition['operator'])
                            {
                                case '>':
                                    if(isset($result[0]))
                                    {
                                        if($result[0]['value'] > $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    }
                                    else
                                    {
                                        if($result['value'] > $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    } 
                                    
                                break;
                                case '<':
                                    if(isset($result[0]))
                                    {
                                        if($result[0]['value'] < $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    }
                                    else
                                    {
                                        if($result['value'] < $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    } 
                                break;
                                case '==':
                                // echo 'result '.$result['value'];exit;
                                    if(isset($result[0]))
                                    {
                                        if($result[0]['value'] == $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    }
                                    else
                                    {
                                        if($result['value'] == $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    } 
                                break;
                                case '<>':                   
                                    if(isset($result[0]))
                                     { 
                                        if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        { //
                                            // echo '502 go';exit;
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            // print_r($path);
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                        }
                                    }
                                    else
                                     { //
                                        if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        { //
                                            // echo '502 go';exit;
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            // print_r($path);
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                        }
                                    }
                                break;
                            }
                            //echo '<pre>';print_r($step);print_r($data);exit;
                            $step=$this->getStepByNumber($data['step'], $params['pathway']);
                            //$step=$this->getNextStep($step,$params);
                            //echo '<pre>';print_r($step);print_r($data);exit;

                            if($step['type']=='question' || $step['type']=='info')
                            {
                                // echo "<script>console.log('598 step ".$step['number']." is question')</script>";
                                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                $data['question']=$st[0];
                                return $data;
                            }
                            else
                            {   
                                // echo "<script>console.log('605 next step ".$step['number']." is ".$step['type'].")</script>";
                                //echo '<pre>';print_r($data);exit;
                                $url = 'api/pw/next_pw/';
                                $myvars = http_build_query($data, '', '&');

                                $ch = curl_init( $url );
                                curl_setopt( $ch, CURLOPT_POST, 1);
                                curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
                                curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
                                curl_setopt( $ch, CURLOPT_HEADER, 0);
                                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

                                curl_exec( $ch );
                            }
                            
                        } 
                    }
                    
                } 
                if($step['type']=='calculation')
                {
                    //echo 'In calculation';exit;
                    // echo "<script>console.log('752 Step ".$step['number']." is calculation')</script>";
                    $st=$this->db->query('select * from step_calculation where step='.$step['id'])->result_array();
                    $stepCalcData=$st[0];
                    if($params['pathway']==5 && $step['number']==12)
                    {
                        $st=$this->db->select('*')
                                ->from('step_answers')
                                ->where('step = '.$stepCalcData['from_step'])
                                ->where('user_id',$params['user_id'])
                                ->where('pathway', $params['pathway'])
                                ->get()
                                ->result_array();
                        $res1=$st[0]['value'];
                        $st=$this->db->select('*')
                                ->from('step_answers')
                                ->where('step = '.$stepCalcData['to_step'])
                                ->where('user_id',$params['user_id'])
                                ->where('pathway', $params['pathway'])
                                ->get()
                                ->result_array();
                        $res2=$st[0]['value'];
                        $result=$res1*$res2;
                    }
                    else
                    {
                        $st=$this->db->select('*')
                                    ->from('step_answers')
                                    ->where('step BETWEEN '.$stepCalcData['from_step'].' and '.$stepCalcData['to_step'].'')
                                    ->where('user_id',$params['user_id'])
                                    ->where('pathway', $params['pathway'])
                                    ->get()
                                    ->result_array();
                        // echo '<pre>';print_r($st);exit;
                        // $st=$this->db->query('select * from step_answers where step BETWEEN '.$stepCalcData['from_step'].' and '.$stepCalcData['to_step'].'')->result_array();
                        
                        if(count($st)>0)
                        {
                            for($i=0;$i<count($st);$i++)
                            {
                                if($params['pathway']==2 )
                                {
                                    if($st[$i]['step']!=7)
                                    {
                                        $result+=$st[$i]['value'];
                                    }                        
                                }
                                else
                                {
                                    $result+=$st[$i]['value'];
                                }
                                
                            }
                        }
                    }
                        
                    // echo "<script>console.log('784 saving result ".$result." for step ".$step['number']."')</script>";
                    $item=array(
                        'pathway' => $params['pathway'],
                        'step'      => $step['number'],
                        'user_id'   => $params['user_id'],
                        'value'     => $result
                    );
                    $st=$this->db->select('*')
                                ->from('step_answers')
                                ->where('step',$step['number'])
                                ->where('user_id',$params['user_id'])
                                ->where('pathway', $params['pathway'])
                                ->get()
                                ->result_array();
                    // print_r($this->db->last_query());exit;
                    if(count($st)>0)
                    {
                        $this->db->where('step',$item['step'])->where('user_id',$params['user_id'])
                                ->where('pathway', $params['pathway'])->update('step_answers',$item);
                    }
                    else
                    {
                        $this->db->insert('step_answers',$item);
                    }
                    // calculate percent
                    $d=count($this->db->select('*')->from('steps')->where('pathway',$params['pathway'])
                                ->get()->result_array());
                    $percent=round(($step['number']/$d)*100);
                    // Save Current Step and save percent in pathway status
                    $item=array(
                            'user_id'   =>  $params['user_id'],
                            'pathway'   =>  $params['pathway'],
                            'current_step'  =>  $step['number'],
                            'percent'   =>  $percent
                    );
                    $st=$this->db->select('*')->from('user_pathway_status')
                                ->where('user_id', $params['user_id'])
                                ->where('pathway', $params['pathway'])
                                ->get()->result_array();
                    if(count($st)>0)
                    {
                        $this->db->where('user_id', $params['user_id'])
                                    ->where('pathway', $params['pathway'])
                                    ->update('user_pathway_status', $item);
                    }
                    else
                    {
                        $this->db->insert('user_pathway_status', $item);
                    }
                    // get next step
                    // print_r($step);exit;
                    $step=$this->getNextStep($step,$params);
                    // print_r($step);exit;
                    $next=$this->db->query('select * from pathflow where step='.$step['number'].' and pathway='.$params['pathway'])->result_array();
                    $data['step']=$step['number'];
                    $data['back']=$next[0]['back'];
                    $data['next']=$next[0]['next'];
                    if($step['type']=='question' || $step['type']=='info')
                    {
                        // echo "<script>console.log('843 next step is question')</script>";
                        $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$data['step'])->result_array();
                        $data['question']=$st[0];
                        return $data;
                    }
                    else
                    {

                        if($step['type']=='condition')
                        {
                            // echo "<script>console.log('853 step ".$step['number']." is condition')</script>";
                            $result=0;
                            $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                            $condition=$st[0];
                            // echo '<pre>';print_r($condition);exit;
                            $d=array();
                            $d['step']=$condition['step_result'];
                            $d['pathway']=$params['pathway'];
                            $d['user_id']=$params['user_id'];

                            $result=$this->getStepAnswer($d);
                            // echo '271 <pre>';print_r($result);exit;
                            //print_r($step['id'].'-'.$params['pathway']); exit;
                            switch($condition['operator'])
                            {
                                case '>':
                                    if(isset($result[0]))
                                    {
                                        if($result[0]['value'] > $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    }
                                    else
                                    {
                                        if($result['value'] > $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    } 
                                    
                                break;
                                case '<':
                                    if(isset($result[0]))
                                    {
                                        if($result[0]['value'] < $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    }
                                    else
                                    {
                                        if($result['value'] < $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    } 
                                break;
                                case '==':
                                // echo 'result '.$result['value'];exit;
                                    if(isset($result[0]))
                                    {
                                        if($result[0]['value'] == $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    }
                                    else
                                    {
                                        if($result['value'] == $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    } 
                                break;
                                case '<>':                   
                                    if(isset($result[0]))
                                     { 
                                        if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        { //
                                            // echo '502 go';exit;
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            // print_r($path);
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                        }
                                    }
                                    else
                                     { //
                                        if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        { //
                                            // echo '502 go';exit;
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            // print_r($path);
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                        }
                                    }
                                break;
                            }
                            //echo '<pre>';print_r($step);print_r($data);exit;
                            $step=$this->getStepByNumber($data['step'], $params['pathway']);
                            //$step=$this->getNextStep($step,$params);
                            //echo '<pre>';print_r($step);print_r($data);exit;

                            if($step['type']=='question' || $step['type']=='info')
                            {
                                // echo "<script>console.log('1048 step ".$step['id']." is question')</script>";
                                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                $data['question']=$st[0];
                                return $data;
                            }
                            else
                            {   
                                // echo "<script>console.log('1055 next step ".$step['number']." not question')</script>";
                                if($step['type']=='condition')
                                {
                                    // echo "<script>console.log('1058 step ".$step['number']." is condition')</script>";
                                    $result=0;
                                    $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                                    $condition=$st[0];
                                    // echo '<pre>';print_r($condition);exit;
                                    $d['step']=$condition['step_result'];
                                    $d['pathway']=$params['pathway'];
                                    $d['user_id']=$params['user_id'];

                                    $result=$this->getStepAnswer($d);
                                    // echo '271 <pre>';print_r($result);exit;
                                    //print_r($step['id'].'-'.$params['pathway']); exit;
                                    // echo "<script>console.log('1070 result is".$result['value']."')</script>";
                                    switch($condition['operator'])
                                    {
                                        case '>':
                                            if(isset($result[0]))
                                            {
                                                if($result[0]['value'] > $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            }
                                            else
                                            {
                                                if($result['value'] > $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            } 
                                            
                                        break;
                                        case '<':
                                            if(isset($result[0]))
                                            {
                                                if($result[0]['value'] < $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            }
                                            else
                                            {
                                                if($result['value'] < $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            } 
                                        break;
                                        case '==':
                                        // echo 'result '.$result['value'];exit;
                                            if(isset($result[0]))
                                            {
                                                if($result[0]['value'] == $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            }
                                            else
                                            {
                                                if($result['value'] == $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            } 
                                        break;
                                        case '<>':                   
                                            if(isset($result[0]))
                                             { 
                                                if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                { //
                                                    // echo '502 go';exit;
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    // print_r($path);
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                }
                                            }
                                            else
                                             { //
                                                if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                { //
                                                    // echo '502 go';exit;
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    // print_r($path);
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                }
                                            }
                                        break;
                                    }
                                    //echo '<pre>';print_r($step);print_r($data);exit;
                                    $step=$this->getStepByNumber($data['step'], $params['pathway']);
                                    //$step=$this->getNextStep($step,$params);
                                    //echo '<pre>';print_r($step);print_r($data);exit;

                                    if($step['type']=='question' || $step['type']=='info')
                                    {
                                        // echo "<script>console.log('1253 step ".$step['number']." is question')</script>";
                                        $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                        $data['question']=$st[0];
                                        return $data;
                                    }
                                    else
                                    {   
                                        // echo "<script>console.log('1260 next step ".$step['number']." is ".$step['type'].")</script>";
                                        //echo '<pre>';print_r($data);exit;
                                        $url = 'api/pw/next_pw/';
                                        $myvars = http_build_query($data, '', '&');

                                        $ch = curl_init( $url );
                                        curl_setopt( $ch, CURLOPT_POST, 1);
                                        curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
                                        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
                                        curl_setopt( $ch, CURLOPT_HEADER, 0);
                                        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

                                        curl_exec( $ch );
                                    }
                                    
                                } 
                            }
                            
                        } 
                        
                    }

                }

            }

        }
        
        if($step['type']=='question' || $step['type']=='info')
        {
            // echo "<script>console.log('630 next step is question')</script>";
            return $data;
        }

        if($step['type']=='condition')
        {
            // echo "<script>console.log('1296 step ".$step['number']." is condition')</script>";
            $result=0;
            $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
            $condition=$st[0];
            // echo '<pre>';print_r($condition);exit;
            $d['step']=$condition['step_result'];
            $d['pathway']=$params['pathway'];
            $d['user_id']=$params['user_id'];

            $result=$this->getStepAnswer($d);
            // echo '271 <pre>';print_r($d);exit;
            if($params['pathway']==3 && $d['step']==2)
            {
                $weight=$result[0]['value'];
                $height=$result[1]['value'];
                $result=array();
                $t=$this->db->select('*')->from('step_answers')
                            ->where('user_id', $params['user_id'])
                            ->where('pathway', $params['pathway'])
                            ->where('step', 2)
                            ->get()
                            ->result_array();
                $result[0]['value']=$t[0]['value'];
                // print_r($result);exit;
                // $result['value']=(($weight)/(($height*$height)/10000));
                // // echo "<script>console.log('1320. Result is '".$result['value'].")</script>";
            }
            else
            {
                if(!$result)
                {
                    $result=1;
                }
            }
            switch($condition['operator'])
            {
                case '>':
                    if(isset($result[0]))
                    {
                        if($result[0]['value'] > $condition['value'])
                        {
                            $data['step']=$condition['if_next_step'];
                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                            $path=$st[0];
                            $data['back']=$step['number'];
                            $data['next']=$path['next'];
                        }
                        else
                        {
                            $data['step']=$condition['else_next_step'];  
                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                            $path=$st[0];
                            $data['back']=$step['number']; 
                            $data['next']=$path['next'];
                            //echo '<pre>';print_r($path);exit;
                        }
                    }
                    else
                    {
                        if($result['value'] > $condition['value'])
                        {
                            $data['step']=$condition['if_next_step'];
                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                            $path=$st[0];
                            $data['back']=$step['number'];
                            $data['next']=$path['next'];
                        }
                        else
                        {
                            $data['step']=$condition['else_next_step'];  
                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                            $path=$st[0];
                            $data['back']=$step['number']; 
                            $data['next']=$path['next'];
                            
                        }
                    } 
                    
                break;
                case '<':
                    if(isset($result[0]))
                    {
                        if($result[0]['value'] < $condition['value'])
                        {
                            $data['step']=$condition['if_next_step'];
                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                            $path=$st[0];
                            $data['back']=$step['number'];
                            $data['next']=$path['next'];
                        }
                        else
                        {
                            $data['step']=$condition['else_next_step'];  
                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                            $path=$st[0];
                            $data['back']=$step['number']; 
                            $data['next']=$path['next'];
                            //echo '<pre>';print_r($path);exit;
                        }
                    }
                    else
                    {
                        if($result['value'] < $condition['value'])
                        {
                            $data['step']=$condition['if_next_step'];
                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                            $path=$st[0];
                            $data['back']=$step['number'];
                            $data['next']=$path['next'];
                        }
                        else
                        {
                            $data['step']=$condition['else_next_step'];  
                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                            $path=$st[0];
                            $data['back']=$step['number']; 
                            $data['next']=$path['next'];
                            //echo '<pre>';print_r($path);exit;
                        }
                    } 
                break;
                case '==':
                // echo 'result '.$result['value'];exit;
                    if(isset($result[0]))
                    {
                        if($result[0]['value'] == $condition['value'])
                        {
                            $data['step']=$condition['if_next_step'];
                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                            $path=$st[0];
                            $data['back']=$step['number'];
                            $data['next']=$path['next'];
                        }
                        else
                        {
                            $data['step']=$condition['else_next_step'];  
                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                            $path=$st[0];
                            $data['back']=$step['number']; 
                            $data['next']=$path['next'];
                            //echo '<pre>';print_r($path);exit;
                        }
                    }
                    else
                    {
                        if($result['value'] == $condition['value'])
                        {
                            $data['step']=$condition['if_next_step'];
                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                            $path=$st[0];
                            $data['back']=$step['number'];
                            $data['next']=$path['next'];
                        }
                        else
                        {
                            $data['step']=$condition['else_next_step'];  
                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                            $path=$st[0];
                            $data['back']=$step['number']; 
                            $data['next']=$path['next'];
                            //echo '<pre>';print_r($path);exit;
                        }
                    } 
                break;
                case '<>':                   
                    if(isset($result[0]))
                    {
                        // print_r($result);
                        // echo "<script>console.log('1462. result of 0 is set')</script>";
                        if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                        {
                            $data['step']=$condition['if_next_step'];
                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                            $path=$st[0];
                            $data['back']=$step['number'];
                            $data['next']=$path['next'];
                            // echo '1471 <pre>';print_r($path);exit;
                        }
                        else
                        {
                            $data['step']=$condition['else_next_step'];  
                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                            $path=$st[0];
                            $data['back']=$step['number']; 
                            $data['next']=$path['next'];
                            // echo '1480 <pre>';print_r($path);exit;
                        }
                        // echo '812 <pre>';print_r($data);exit;
                    }
                    else
                    {
                        $min=$condition['value_from'];
                        $max=$condition['value_to'];
                        // echo "<script>console.log('1486. result of 0 not set')</script>";
                        if($result['value'] >= $min && $result['value'] <= $max)
                        {
                            // echo 'Result value '.$result['value'].' is greater than '.$min.' and smaller than '.$max;
                            $data['step']=$condition['if_next_step'];
                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                            $path=$st[0];
                            $data['back']=$step['number'];
                            $data['next']=$path['next'];
                        }
                        else
                        {
                            // echo 'Result value '.$result['value'].' is not in range';
                            $data['step']=$condition['else_next_step'];  
                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                            $path=$st[0];
                            $data['back']=$step['number']; 
                            $data['next']=$path['next'];
                            
                        }
                        // echo ' 834 <pre>';print_r($data);
                    } 
                break;
            }
                
            
            $step=$this->getStepByNumber($data['step'], $params['pathway']);
            // echo '<pre>';print_r($step);print_r($data);exit;

            if($step['type']=='question' || $step['type']=='info')
            {
                // echo "<script>console.log('1516. Next Step '".$step['id']." is ".$step['type'].")</script>";
                // echo 'next step q';exit;
                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                $data['question']=$st[0];
                return $data;
            }
            else
            {   
                // echo "<script>console.log('1524. Next Step ".$step['number']." is ".$step['type'].")</script>";
                if($step['type']=='condition')
                {
                    $result=0;
                    $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                    $condition=$st[0];
                    // echo '<pre>';print_r($condition);exit;
                    $d['step']=$condition['step_result'];
                    $d['pathway']=$params['pathway'];
                    $d['user_id']=$params['user_id'];

                    $result=$this->getStepAnswer($d);
                    // echo '271 <pre>';print_r($result);exit;

                    if($params['pathway']==3 && $d['step']==2)
                    {
                        $weight=$result[0]['value'];
                        $height=$result[1]['value'];
                        $result=array();
                        $t=$this->db->select('*')->from('step_answers')
                                    ->where('user_id', $params['user_id'])
                                    ->where('pathway', $params['pathway'])
                                    ->where('step', 2)
                                    ->get()
                                    ->result_array();
                        $result[0]['value']=$t[0]['value'];
                        // print_r($result);exit;
                        // // echo "<script>console.log('1550. Result is '".$result['value'].")</script>";
                    }
                    else
                    {
                        if(!$result)
                        {
                            $result=1;
                        }
                    }
                    switch($condition['operator'])
                    {
                        case '>':
                            if(isset($result[0]))
                            {
                                if($result[0]['value'] > $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    // echo '1582 <pre>';print_r($data);exit;
                                }
                            }
                            else
                            {
                                if($result['value'] > $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    // echo '1602 <pre>';print_r($path);exit;
                                }
                            } 
                            
                        break;
                        case '<':
                            if(isset($result[0]))
                            {
                                if($result[0]['value'] < $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            }
                            else
                            {
                                if($result['value'] < $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            } 
                        break;
                        case '==':
                        // echo 'result '.$result['value'];exit;
                            if(isset($result[0]))
                            {
                                if($result[0]['value'] == $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            }
                            else
                            {
                                if($result['value'] == $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            } 
                        break;
                        case '<>':                   
                            if(isset($result[0]))
                             { 
                                if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                { //
                                    // echo '502 go';exit;
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    // print_r($path);
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                }
                            }
                            else
                             { //
                                if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                { //
                                    // echo '502 go';exit;
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    // print_r($path);
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                }
                            }
                        break;
                    }
                    // echo '<pre>';print_r($step);print_r($data);exit;
                    $step=$this->getStepByNumber($data['step'], $params['pathway']);
                    // echo "<script>console.log('1736. Next Step ".$step['number']." is ".$step['type'].")</script>";
                    if($step['type']=='question' || $step['type']=='info')
                    {
                        // echo 'next step q';exit;
                        $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                        $data['question']=$st[0];
                        return $data;
                    }
                    else
                    {   
                        if($step['type']=='condition')
                        {
                            $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                            $condition=$st[0];
                            // echo '1075 <pre>';print_r($condition);exit;
                            $d['step']=$condition['step_result'];
                            $d['pathway']=$params['pathway'];
                            $d['user_id']=$params['user_id'];

                            $result=$this->getStepAnswer($d);
                            // echo '271 <pre>';print_r($result);exit;

                            if($params['pathway']==3 && $d['step']==2)
                            {
                                $weight=$result[0]['value'];
                                $height=$result[1]['value'];
                                $result=array();
                                $t=$this->db->select('*')->from('step_answers')
                                            ->where('user_id', $params['user_id'])
                                            ->where('pathway', $params['pathway'])
                                            ->where('step', 2)
                                            ->get()
                                            ->result_array();
                                $result[0]['value']=$t[0]['value'];
                                // echo "<script>console.log('1089. Result is '".$result['value'].")</script>";

                            }
                            else
                            {
                                if(!$result)
                                {
                                    $result=1;
                                }
                            }
                            switch($condition['operator'])
                            {
                                case '>':
                                    if(isset($result[0]))
                                    {
                                        if($result[0]['value'] > $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    }
                                    else
                                    {
                                        if($result['value'] > $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    } 
                                    
                                break;
                                case '<':
                                    if(isset($result[0]))
                                    {
                                        if($result[0]['value'] < $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    }
                                    else
                                    {
                                        if($result['value'] < $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    } 
                                break;
                                case '==':
                                // echo 'result '.$result['value'];exit;
                                    if(isset($result[0]))
                                    {
                                        if($result[0]['value'] == $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    }
                                    else
                                    {
                                        if($result['value'] == $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    } 
                                break;
                                case '<>':                   
                                    if(isset($result[0]))
                                     { 
                                        if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        { //
                                            // echo '502 go';exit;
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            // print_r($path);
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                        }
                                    }
                                    else
                                     { //
                                        if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        { //
                                            // echo '502 go';exit;
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            // print_r($path);
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                        }
                                    }
                                break;
                            }
                            // echo '<pre>';print_r($step);print_r($data);exit;
                            $step=$this->getStepByNumber($data['step'], $params['pathway']);
                            // echo "<script>console.log('1274. Next Step ".$step['number']." is ".$step['type'].")</script>";
                            if($step['type']=='question' || $step['type']=='info')
                            {
                                // echo 'next step q';exit;
                                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                $data['question']=$st[0];
                                return $data;
                            }
                            if($step['type']=='condition')
                            {
                                $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                                $condition=$st[0];
                                // echo '1075 <pre>';print_r($condition);exit;
                                $d['step']=$condition['step_result'];
                                $d['pathway']=$params['pathway'];
                                $d['user_id']=$params['user_id'];

                                $result=$this->getStepAnswer($d);
                                // echo '271 <pre>';print_r($result);exit;

                                if($params['pathway']==3 && $d['step']==2)
                                {
                                    $weight=$result[0]['value'];
                                    $height=$result[1]['value'];
                                    $result=array();
                                    $t=$this->db->select('*')->from('step_answers')
                                                ->where('user_id', $params['user_id'])
                                                ->where('pathway', $params['pathway'])
                                                ->where('step', 2)
                                                ->get()
                                                ->result_array();
                                    $result[0]['value']=$t[0]['value'];
                                    // echo "<script>console.log('1989. Result is '".$result['value'].")</script>";

                                }
                                else
                                {
                                    if(!$result)
                                    {
                                        $result=1;
                                    }
                                }
                                switch($condition['operator'])
                                {
                                    case '>':
                                        if(isset($result[0]))
                                        {
                                            if($result[0]['value'] > $condition['value'])
                                            {
                                                $data['step']=$condition['if_next_step'];
                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number'];
                                                $data['next']=$path['next'];
                                            }
                                            else
                                            {
                                                $data['step']=$condition['else_next_step'];  
                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number']; 
                                                $data['next']=$path['next'];
                                                //echo '<pre>';print_r($path);exit;
                                            }
                                        }
                                        else
                                        {
                                            if($result['value'] > $condition['value'])
                                            {
                                                $data['step']=$condition['if_next_step'];
                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number'];
                                                $data['next']=$path['next'];
                                            }
                                            else
                                            {
                                                $data['step']=$condition['else_next_step'];  
                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number']; 
                                                $data['next']=$path['next'];
                                                //echo '<pre>';print_r($path);exit;
                                            }
                                        } 
                                        
                                    break;
                                    case '<':
                                        if(isset($result[0]))
                                        {
                                            if($result[0]['value'] < $condition['value'])
                                            {
                                                $data['step']=$condition['if_next_step'];
                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number'];
                                                $data['next']=$path['next'];
                                            }
                                            else
                                            {
                                                $data['step']=$condition['else_next_step'];  
                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number']; 
                                                $data['next']=$path['next'];
                                                //echo '<pre>';print_r($path);exit;
                                            }
                                        }
                                        else
                                        {
                                            if($result['value'] < $condition['value'])
                                            {
                                                $data['step']=$condition['if_next_step'];
                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number'];
                                                $data['next']=$path['next'];
                                            }
                                            else
                                            {
                                                $data['step']=$condition['else_next_step'];  
                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number']; 
                                                $data['next']=$path['next'];
                                                //echo '<pre>';print_r($path);exit;
                                            }
                                        } 
                                    break;
                                    case '==':
                                    // echo 'result '.$result['value'];exit;
                                        if(isset($result[0]))
                                        {
                                            if($result[0]['value'] == $condition['value'])
                                            {
                                                $data['step']=$condition['if_next_step'];
                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number'];
                                                $data['next']=$path['next'];
                                            }
                                            else
                                            {
                                                $data['step']=$condition['else_next_step'];  
                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number']; 
                                                $data['next']=$path['next'];
                                                //echo '<pre>';print_r($path);exit;
                                            }
                                        }
                                        else
                                        {
                                            if($result['value'] == $condition['value'])
                                            {
                                                $data['step']=$condition['if_next_step'];
                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number'];
                                                $data['next']=$path['next'];
                                            }
                                            else
                                            {
                                                $data['step']=$condition['else_next_step'];  
                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number']; 
                                                $data['next']=$path['next'];
                                                //echo '<pre>';print_r($path);exit;
                                            }
                                        } 
                                    break;
                                    case '<>':                   
                                        if(isset($result[0]))
                                         { 
                                            if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                            {
                                                $data['step']=$condition['if_next_step'];
                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number'];
                                                $data['next']=$path['next'];
                                            }
                                            else
                                            { //
                                                // echo '502 go';exit;
                                                $data['step']=$condition['else_next_step'];  
                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                // print_r($path);
                                                $data['back']=$step['number']; 
                                                $data['next']=$path['next'];
                                            }
                                        }
                                        else
                                         { //
                                            if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                            {
                                                $data['step']=$condition['if_next_step'];
                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number'];
                                                $data['next']=$path['next'];
                                            }
                                            else
                                            { //
                                                // echo '502 go';exit;
                                                $data['step']=$condition['else_next_step'];  
                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                // print_r($path);
                                                $data['back']=$step['number']; 
                                                $data['next']=$path['next'];
                                            }
                                        }
                                    break;
                                }
                                // echo '<pre>';print_r($step);print_r($data);exit;
                                $step=$this->getStepByNumber($data['step'], $params['pathway']);
                                // echo "<script>console.log('2176. Next Step ".$step['number']." is ".$step['type'].")</script>";
                                if($step['type']=='question' || $step['type']=='info')
                                {
                                    // echo 'next step q';exit;
                                    $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                    $data['question']=$st[0];
                                    return $data;
                                }
                                if($step['type']=='condition')
                                {
                                    $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                                    $condition=$st[0];
                                    // echo '1075 <pre>';print_r($condition);exit;
                                    $d['step']=$condition['step_result'];
                                    $d['pathway']=$params['pathway'];
                                    $d['user_id']=$params['user_id'];

                                    $result=$this->getStepAnswer($d);
                                    // echo '271 <pre>';print_r($result);exit;

                                    if($params['pathway']==3 && $d['step']==2)
                                    {
                                        $weight=$result[0]['value'];
                                        $height=$result[1]['value'];
                                        $result=array();
                                        $t=$this->db->select('*')->from('step_answers')
                                                    ->where('user_id', $params['user_id'])
                                                    ->where('pathway', $params['pathway'])
                                                    ->where('step', 2)
                                                    ->get()
                                                    ->result_array();
                                        $result[0]['value']=$t[0]['value'];
                                        // echo "<script>console.log('1989. Result is '".$result['value'].")</script>";

                                    }
                                    else
                                    {
                                        if(!$result)
                                        {
                                            $result=1;
                                        }
                                    }
                                    switch($condition['operator'])
                                    {
                                        case '>':
                                            if(isset($result[0]))
                                            {
                                                if($result[0]['value'] > $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            }
                                            else
                                            {
                                                if($result['value'] > $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            } 
                                            
                                        break;
                                        case '<':
                                            if(isset($result[0]))
                                            {
                                                if($result[0]['value'] < $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            }
                                            else
                                            {
                                                if($result['value'] < $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            } 
                                        break;
                                        case '==':
                                        // echo 'result '.$result['value'];exit;
                                            if(isset($result[0]))
                                            {
                                                if($result[0]['value'] == $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            }
                                            else
                                            {
                                                if($result['value'] == $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            } 
                                        break;
                                        case '<>':                   
                                            if(isset($result[0]))
                                             { 
                                                if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                { //
                                                    // echo '502 go';exit;
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    // print_r($path);
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                }
                                            }
                                            else
                                             { //
                                                if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                { //
                                                    // echo '502 go';exit;
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    // print_r($path);
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                }
                                            }
                                        break;
                                    }
                                    // echo '<pre>';print_r($step);print_r($data);exit;
                                    $step=$this->getStepByNumber($data['step'], $params['pathway']);
                                    // echo "<script>console.log('2395. Next Step ".$step['number']." is ".$step['type'].")</script>";
                                    if($step['type']=='question' || $step['type']=='info')
                                    {
                                        // echo 'next step q';exit;
                                        $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                        $data['question']=$st[0];
                                        return $data;
                                    }
                                    if($step['type']=='condition')
                                    {
                                        $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                                        $condition=$st[0];
                                        // echo '1075 <pre>';print_r($condition);exit;
                                        $d['step']=$condition['step_result'];
                                        $d['pathway']=$params['pathway'];
                                        $d['user_id']=$params['user_id'];

                                        $result=$this->getStepAnswer($d);
                                        // echo '271 <pre>';print_r($result);exit;

                                        if($params['pathway']==3 && $d['step']==2)
                                        {
                                            $weight=$result[0]['value'];
                                            $height=$result[1]['value'];
                                            $result=array();
                                            $t=$this->db->select('*')->from('step_answers')
                                                        ->where('user_id', $params['user_id'])
                                                        ->where('pathway', $params['pathway'])
                                                        ->where('step', 2)
                                                        ->get()
                                                        ->result_array();
                                            $result[0]['value']=$t[0]['value'];
                                            // echo "<script>console.log('1989. Result is '".$result['value'].")</script>";

                                        }
                                        else
                                        {
                                            if(!$result)
                                            {
                                                $result=1;
                                            }
                                        }
                                        switch($condition['operator'])
                                        {
                                            case '>':
                                                if(isset($result[0]))
                                                {
                                                    if($result[0]['value'] > $condition['value'])
                                                    {
                                                        $data['step']=$condition['if_next_step'];
                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                        $path=$st[0];
                                                        $data['back']=$step['number'];
                                                        $data['next']=$path['next'];
                                                    }
                                                    else
                                                    {
                                                        $data['step']=$condition['else_next_step'];  
                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                        $path=$st[0];
                                                        $data['back']=$step['number']; 
                                                        $data['next']=$path['next'];
                                                        //echo '<pre>';print_r($path);exit;
                                                    }
                                                }
                                                else
                                                {
                                                    if($result['value'] > $condition['value'])
                                                    {
                                                        $data['step']=$condition['if_next_step'];
                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                        $path=$st[0];
                                                        $data['back']=$step['number'];
                                                        $data['next']=$path['next'];
                                                    }
                                                    else
                                                    {
                                                        $data['step']=$condition['else_next_step'];  
                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                        $path=$st[0];
                                                        $data['back']=$step['number']; 
                                                        $data['next']=$path['next'];
                                                        //echo '<pre>';print_r($path);exit;
                                                    }
                                                } 
                                                
                                            break;
                                            case '<':
                                                if(isset($result[0]))
                                                {
                                                    if($result[0]['value'] < $condition['value'])
                                                    {
                                                        $data['step']=$condition['if_next_step'];
                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                        $path=$st[0];
                                                        $data['back']=$step['number'];
                                                        $data['next']=$path['next'];
                                                    }
                                                    else
                                                    {
                                                        $data['step']=$condition['else_next_step'];  
                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                        $path=$st[0];
                                                        $data['back']=$step['number']; 
                                                        $data['next']=$path['next'];
                                                        //echo '<pre>';print_r($path);exit;
                                                    }
                                                }
                                                else
                                                {
                                                    if($result['value'] < $condition['value'])
                                                    {
                                                        $data['step']=$condition['if_next_step'];
                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                        $path=$st[0];
                                                        $data['back']=$step['number'];
                                                        $data['next']=$path['next'];
                                                    }
                                                    else
                                                    {
                                                        $data['step']=$condition['else_next_step'];  
                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                        $path=$st[0];
                                                        $data['back']=$step['number']; 
                                                        $data['next']=$path['next'];
                                                        //echo '<pre>';print_r($path);exit;
                                                    }
                                                } 
                                            break;
                                            case '==':
                                            // echo 'result '.$result['value'];exit;
                                                if(isset($result[0]))
                                                {
                                                    if($result[0]['value'] == $condition['value'])
                                                    {
                                                        $data['step']=$condition['if_next_step'];
                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                        $path=$st[0];
                                                        $data['back']=$step['number'];
                                                        $data['next']=$path['next'];
                                                    }
                                                    else
                                                    {
                                                        $data['step']=$condition['else_next_step'];  
                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                        $path=$st[0];
                                                        $data['back']=$step['number']; 
                                                        $data['next']=$path['next'];
                                                        //echo '<pre>';print_r($path);exit;
                                                    }
                                                }
                                                else
                                                {
                                                    if($result['value'] == $condition['value'])
                                                    {
                                                        $data['step']=$condition['if_next_step'];
                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                        $path=$st[0];
                                                        $data['back']=$step['number'];
                                                        $data['next']=$path['next'];
                                                    }
                                                    else
                                                    {
                                                        $data['step']=$condition['else_next_step'];  
                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                        $path=$st[0];
                                                        $data['back']=$step['number']; 
                                                        $data['next']=$path['next'];
                                                        //echo '<pre>';print_r($path);exit;
                                                    }
                                                } 
                                            break;
                                            case '<>':                   
                                                if(isset($result[0]))
                                                 { 
                                                    if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                                    {
                                                        $data['step']=$condition['if_next_step'];
                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                        $path=$st[0];
                                                        $data['back']=$step['number'];
                                                        $data['next']=$path['next'];
                                                    }
                                                    else
                                                    { //
                                                        // echo '502 go';exit;
                                                        $data['step']=$condition['else_next_step'];  
                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                        $path=$st[0];
                                                        // print_r($path);
                                                        $data['back']=$step['number']; 
                                                        $data['next']=$path['next'];
                                                    }
                                                }
                                                else
                                                 { //
                                                    if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                                    {
                                                        $data['step']=$condition['if_next_step'];
                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                        $path=$st[0];
                                                        $data['back']=$step['number'];
                                                        $data['next']=$path['next'];
                                                    }
                                                    else
                                                    { //
                                                        // echo '502 go';exit;
                                                        $data['step']=$condition['else_next_step'];  
                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                        $path=$st[0];
                                                        // print_r($path);
                                                        $data['back']=$step['number']; 
                                                        $data['next']=$path['next'];
                                                    }
                                                }
                                            break;
                                        }
                                        // echo '<pre>';print_r($step);print_r($data);exit;
                                        $step=$this->getStepByNumber($data['step'], $params['pathway']);
                                        // echo "<script>console.log('2614. Next Step ".$step['number']." is ".$step['type'].")</script>";
                                        if($step['type']=='question' || $step['type']=='info')
                                        {
                                            // echo 'next step q';exit;
                                            $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                            $data['question']=$st[0];
                                            return $data;
                                        }
                                        if($step['type']=='condition')
                                        {
                                            $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                                            $condition=$st[0];
                                            // echo '1075 <pre>';print_r($condition);exit;
                                            $d['step']=$condition['step_result'];
                                            $d['pathway']=$params['pathway'];
                                            $d['user_id']=$params['user_id'];

                                            $result=$this->getStepAnswer($d);
                                            // echo '271 <pre>';print_r($result);exit;

                                            if($params['pathway']==3 && $d['step']==2)
                                            {
                                                $weight=$result[0]['value'];
                                                $height=$result[1]['value'];
                                                $result=array();
                                                $t=$this->db->select('*')->from('step_answers')
                                                            ->where('user_id', $params['user_id'])
                                                            ->where('pathway', $params['pathway'])
                                                            ->where('step', 2)
                                                            ->get()
                                                            ->result_array();
                                                $result[0]['value']=$t[0]['value'];
                                                // echo "<script>console.log('1989. Result is '".$result['value'].")</script>";

                                            }
                                            else
                                            {
                                                if(!$result)
                                                {
                                                    $result=1;
                                                }
                                            }
                                            switch($condition['operator'])
                                            {
                                                case '>':
                                                    if(isset($result[0]))
                                                    {
                                                        if($result[0]['value'] > $condition['value'])
                                                        {
                                                            $data['step']=$condition['if_next_step'];
                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                            $path=$st[0];
                                                            $data['back']=$step['number'];
                                                            $data['next']=$path['next'];
                                                        }
                                                        else
                                                        {
                                                            $data['step']=$condition['else_next_step'];  
                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                            $path=$st[0];
                                                            $data['back']=$step['number']; 
                                                            $data['next']=$path['next'];
                                                            //echo '<pre>';print_r($path);exit;
                                                        }
                                                    }
                                                    else
                                                    {
                                                        if($result['value'] > $condition['value'])
                                                        {
                                                            $data['step']=$condition['if_next_step'];
                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                            $path=$st[0];
                                                            $data['back']=$step['number'];
                                                            $data['next']=$path['next'];
                                                        }
                                                        else
                                                        {
                                                            $data['step']=$condition['else_next_step'];  
                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                            $path=$st[0];
                                                            $data['back']=$step['number']; 
                                                            $data['next']=$path['next'];
                                                            //echo '<pre>';print_r($path);exit;
                                                        }
                                                    } 
                                                    
                                                break;
                                                case '<':
                                                    if(isset($result[0]))
                                                    {
                                                        if($result[0]['value'] < $condition['value'])
                                                        {
                                                            $data['step']=$condition['if_next_step'];
                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                            $path=$st[0];
                                                            $data['back']=$step['number'];
                                                            $data['next']=$path['next'];
                                                        }
                                                        else
                                                        {
                                                            $data['step']=$condition['else_next_step'];  
                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                            $path=$st[0];
                                                            $data['back']=$step['number']; 
                                                            $data['next']=$path['next'];
                                                            //echo '<pre>';print_r($path);exit;
                                                        }
                                                    }
                                                    else
                                                    {
                                                        if($result['value'] < $condition['value'])
                                                        {
                                                            $data['step']=$condition['if_next_step'];
                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                            $path=$st[0];
                                                            $data['back']=$step['number'];
                                                            $data['next']=$path['next'];
                                                        }
                                                        else
                                                        {
                                                            $data['step']=$condition['else_next_step'];  
                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                            $path=$st[0];
                                                            $data['back']=$step['number']; 
                                                            $data['next']=$path['next'];
                                                            //echo '<pre>';print_r($path);exit;
                                                        }
                                                    } 
                                                break;
                                                case '==':
                                                // echo 'result '.$result['value'];exit;
                                                    if(isset($result[0]))
                                                    {
                                                        if($result[0]['value'] == $condition['value'])
                                                        {
                                                            $data['step']=$condition['if_next_step'];
                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                            $path=$st[0];
                                                            $data['back']=$step['number'];
                                                            $data['next']=$path['next'];
                                                        }
                                                        else
                                                        {
                                                            $data['step']=$condition['else_next_step'];  
                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                            $path=$st[0];
                                                            $data['back']=$step['number']; 
                                                            $data['next']=$path['next'];
                                                            //echo '<pre>';print_r($path);exit;
                                                        }
                                                    }
                                                    else
                                                    {
                                                        if($result['value'] == $condition['value'])
                                                        {
                                                            $data['step']=$condition['if_next_step'];
                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                            $path=$st[0];
                                                            $data['back']=$step['number'];
                                                            $data['next']=$path['next'];
                                                        }
                                                        else
                                                        {
                                                            $data['step']=$condition['else_next_step'];  
                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                            $path=$st[0];
                                                            $data['back']=$step['number']; 
                                                            $data['next']=$path['next'];
                                                            //echo '<pre>';print_r($path);exit;
                                                        }
                                                    } 
                                                break;
                                                case '<>':                   
                                                    if(isset($result[0]))
                                                     { 
                                                        if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                                        {
                                                            $data['step']=$condition['if_next_step'];
                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                            $path=$st[0];
                                                            $data['back']=$step['number'];
                                                            $data['next']=$path['next'];
                                                        }
                                                        else
                                                        { //
                                                            // echo '502 go';exit;
                                                            $data['step']=$condition['else_next_step'];  
                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                            $path=$st[0];
                                                            // print_r($path);
                                                            $data['back']=$step['number']; 
                                                            $data['next']=$path['next'];
                                                        }
                                                    }
                                                    else
                                                     { //
                                                        if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                                        {
                                                            $data['step']=$condition['if_next_step'];
                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                            $path=$st[0];
                                                            $data['back']=$step['number'];
                                                            $data['next']=$path['next'];
                                                        }
                                                        else
                                                        { //
                                                            // echo '502 go';exit;
                                                            $data['step']=$condition['else_next_step'];  
                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                            $path=$st[0];
                                                            // print_r($path);
                                                            $data['back']=$step['number']; 
                                                            $data['next']=$path['next'];
                                                        }
                                                    }
                                                break;
                                            }
                                            // echo '<pre>';print_r($step);print_r($data);exit;
                                            $step=$this->getStepByNumber($data['step'], $params['pathway']);
                                            // echo "<script>console.log('2833. Next Step ".$step['number']." is ".$step['type'].")</script>";
                                            if($step['type']=='question' || $step['type']=='info')
                                            {
                                                // echo 'next step q';exit;
                                                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                                $data['question']=$st[0];
                                                return $data;
                                            }
                                            if($step['type']=='condition')
                                            {
                                                $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                                                $condition=$st[0];
                                                // echo '1075 <pre>';print_r($condition);exit;
                                                $d['step']=$condition['step_result'];
                                                $d['pathway']=$params['pathway'];
                                                $d['user_id']=$params['user_id'];

                                                $result=$this->getStepAnswer($d);
                                                // echo '271 <pre>';print_r($result);exit;

                                                if($params['pathway']==3 && $d['step']==2)
                                                {
                                                    $weight=$result[0]['value'];
                                                    $height=$result[1]['value'];
                                                    $result=array();
                                                    $t=$this->db->select('*')->from('step_answers')
                                                                ->where('user_id', $params['user_id'])
                                                                ->where('pathway', $params['pathway'])
                                                                ->where('step', 2)
                                                                ->get()
                                                                ->result_array();
                                                    $result[0]['value']=$t[0]['value'];
                                                    // echo "<script>console.log('1989. Result is '".$result['value'].")</script>";

                                                }
                                                else
                                                {
                                                    if(!$result)
                                                    {
                                                        $result=1;
                                                    }
                                                }
                                                switch($condition['operator'])
                                                {
                                                    case '>':
                                                        if(isset($result[0]))
                                                        {
                                                            if($result[0]['value'] > $condition['value'])
                                                            {
                                                                $data['step']=$condition['if_next_step'];
                                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                $path=$st[0];
                                                                $data['back']=$step['number'];
                                                                $data['next']=$path['next'];
                                                            }
                                                            else
                                                            {
                                                                $data['step']=$condition['else_next_step'];  
                                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                $path=$st[0];
                                                                $data['back']=$step['number']; 
                                                                $data['next']=$path['next'];
                                                                //echo '<pre>';print_r($path);exit;
                                                            }
                                                        }
                                                        else
                                                        {
                                                            if($result['value'] > $condition['value'])
                                                            {
                                                                $data['step']=$condition['if_next_step'];
                                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                $path=$st[0];
                                                                $data['back']=$step['number'];
                                                                $data['next']=$path['next'];
                                                            }
                                                            else
                                                            {
                                                                $data['step']=$condition['else_next_step'];  
                                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                $path=$st[0];
                                                                $data['back']=$step['number']; 
                                                                $data['next']=$path['next'];
                                                                //echo '<pre>';print_r($path);exit;
                                                            }
                                                        } 
                                                        
                                                    break;
                                                    case '<':
                                                        if(isset($result[0]))
                                                        {
                                                            if($result[0]['value'] < $condition['value'])
                                                            {
                                                                $data['step']=$condition['if_next_step'];
                                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                $path=$st[0];
                                                                $data['back']=$step['number'];
                                                                $data['next']=$path['next'];
                                                            }
                                                            else
                                                            {
                                                                $data['step']=$condition['else_next_step'];  
                                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                $path=$st[0];
                                                                $data['back']=$step['number']; 
                                                                $data['next']=$path['next'];
                                                                //echo '<pre>';print_r($path);exit;
                                                            }
                                                        }
                                                        else
                                                        {
                                                            if($result['value'] < $condition['value'])
                                                            {
                                                                $data['step']=$condition['if_next_step'];
                                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                $path=$st[0];
                                                                $data['back']=$step['number'];
                                                                $data['next']=$path['next'];
                                                            }
                                                            else
                                                            {
                                                                $data['step']=$condition['else_next_step'];  
                                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                $path=$st[0];
                                                                $data['back']=$step['number']; 
                                                                $data['next']=$path['next'];
                                                                //echo '<pre>';print_r($path);exit;
                                                            }
                                                        } 
                                                    break;
                                                    case '==':
                                                    // echo 'result '.$result['value'];exit;
                                                        if(isset($result[0]))
                                                        {
                                                            if($result[0]['value'] == $condition['value'])
                                                            {
                                                                $data['step']=$condition['if_next_step'];
                                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                $path=$st[0];
                                                                $data['back']=$step['number'];
                                                                $data['next']=$path['next'];
                                                            }
                                                            else
                                                            {
                                                                $data['step']=$condition['else_next_step'];  
                                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                $path=$st[0];
                                                                $data['back']=$step['number']; 
                                                                $data['next']=$path['next'];
                                                                //echo '<pre>';print_r($path);exit;
                                                            }
                                                        }
                                                        else
                                                        {
                                                            if($result['value'] == $condition['value'])
                                                            {
                                                                $data['step']=$condition['if_next_step'];
                                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                $path=$st[0];
                                                                $data['back']=$step['number'];
                                                                $data['next']=$path['next'];
                                                            }
                                                            else
                                                            {
                                                                $data['step']=$condition['else_next_step'];  
                                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                $path=$st[0];
                                                                $data['back']=$step['number']; 
                                                                $data['next']=$path['next'];
                                                                //echo '<pre>';print_r($path);exit;
                                                            }
                                                        } 
                                                    break;
                                                    case '<>':                   
                                                        if(isset($result[0]))
                                                         { 
                                                            if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                                            {
                                                                $data['step']=$condition['if_next_step'];
                                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                $path=$st[0];
                                                                $data['back']=$step['number'];
                                                                $data['next']=$path['next'];
                                                            }
                                                            else
                                                            { //
                                                                // echo '502 go';exit;
                                                                $data['step']=$condition['else_next_step'];  
                                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                $path=$st[0];
                                                                // print_r($path);
                                                                $data['back']=$step['number']; 
                                                                $data['next']=$path['next'];
                                                            }
                                                        }
                                                        else
                                                         { //
                                                            if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                                            {
                                                                $data['step']=$condition['if_next_step'];
                                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                $path=$st[0];
                                                                $data['back']=$step['number'];
                                                                $data['next']=$path['next'];
                                                            }
                                                            else
                                                            { //
                                                                // echo '502 go';exit;
                                                                $data['step']=$condition['else_next_step'];  
                                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                $path=$st[0];
                                                                // print_r($path);
                                                                $data['back']=$step['number']; 
                                                                $data['next']=$path['next'];
                                                            }
                                                        }
                                                    break;
                                                }
                                                // echo '<pre>';print_r($step);print_r($data);exit;
                                                $step=$this->getStepByNumber($data['step'], $params['pathway']);
                                                // echo "<script>console.log('3052. Next Step ".$step['number']." is ".$step['type'].")</script>";
                                                if($step['type']=='question' || $step['type']=='info')
                                                {
                                                    // echo 'next step q';exit;
                                                    $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                                    $data['question']=$st[0];
                                                    return $data;
                                                }
                                                if($step['type']=='condition')
                                                {
                                                    $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                                                    $condition=$st[0];
                                                    // echo '1075 <pre>';print_r($condition);exit;
                                                    $d['step']=$condition['step_result'];
                                                    $d['pathway']=$params['pathway'];
                                                    $d['user_id']=$params['user_id'];

                                                    $result=$this->getStepAnswer($d);
                                                    // echo '271 <pre>';print_r($result);exit;

                                                    if($params['pathway']==3 && $d['step']==2)
                                                    {
                                                        $weight=$result[0]['value'];
                                                        $height=$result[1]['value'];
                                                        $result=array();
                                                        $t=$this->db->select('*')->from('step_answers')
                                                                    ->where('user_id', $params['user_id'])
                                                                    ->where('pathway', $params['pathway'])
                                                                    ->where('step', 2)
                                                                    ->get()
                                                                    ->result_array();
                                                        $result[0]['value']=$t[0]['value'];
                                                        // echo "<script>console.log('1989. Result is '".$result['value'].")</script>";

                                                    }
                                                    else
                                                    {
                                                        if(!$result)
                                                        {
                                                            $result=1;
                                                        }
                                                    }
                                                    switch($condition['operator'])
                                                    {
                                                        case '>':
                                                            if(isset($result[0]))
                                                            {
                                                                if($result[0]['value'] > $condition['value'])
                                                                {
                                                                    $data['step']=$condition['if_next_step'];
                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                    $path=$st[0];
                                                                    $data['back']=$step['number'];
                                                                    $data['next']=$path['next'];
                                                                }
                                                                else
                                                                {
                                                                    $data['step']=$condition['else_next_step'];  
                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                    $path=$st[0];
                                                                    $data['back']=$step['number']; 
                                                                    $data['next']=$path['next'];
                                                                    //echo '<pre>';print_r($path);exit;
                                                                }
                                                            }
                                                            else
                                                            {
                                                                if($result['value'] > $condition['value'])
                                                                {
                                                                    $data['step']=$condition['if_next_step'];
                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                    $path=$st[0];
                                                                    $data['back']=$step['number'];
                                                                    $data['next']=$path['next'];
                                                                }
                                                                else
                                                                {
                                                                    $data['step']=$condition['else_next_step'];  
                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                    $path=$st[0];
                                                                    $data['back']=$step['number']; 
                                                                    $data['next']=$path['next'];
                                                                    //echo '<pre>';print_r($path);exit;
                                                                }
                                                            } 
                                                            
                                                        break;
                                                        case '<':
                                                            if(isset($result[0]))
                                                            {
                                                                if($result[0]['value'] < $condition['value'])
                                                                {
                                                                    $data['step']=$condition['if_next_step'];
                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                    $path=$st[0];
                                                                    $data['back']=$step['number'];
                                                                    $data['next']=$path['next'];
                                                                }
                                                                else
                                                                {
                                                                    $data['step']=$condition['else_next_step'];  
                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                    $path=$st[0];
                                                                    $data['back']=$step['number']; 
                                                                    $data['next']=$path['next'];
                                                                    //echo '<pre>';print_r($path);exit;
                                                                }
                                                            }
                                                            else
                                                            {
                                                                if($result['value'] < $condition['value'])
                                                                {
                                                                    $data['step']=$condition['if_next_step'];
                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                    $path=$st[0];
                                                                    $data['back']=$step['number'];
                                                                    $data['next']=$path['next'];
                                                                }
                                                                else
                                                                {
                                                                    $data['step']=$condition['else_next_step'];  
                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                    $path=$st[0];
                                                                    $data['back']=$step['number']; 
                                                                    $data['next']=$path['next'];
                                                                    //echo '<pre>';print_r($path);exit;
                                                                }
                                                            } 
                                                        break;
                                                        case '==':
                                                        // echo 'result '.$result['value'];exit;
                                                            if(isset($result[0]))
                                                            {
                                                                if($result[0]['value'] == $condition['value'])
                                                                {
                                                                    $data['step']=$condition['if_next_step'];
                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                    $path=$st[0];
                                                                    $data['back']=$step['number'];
                                                                    $data['next']=$path['next'];
                                                                }
                                                                else
                                                                {
                                                                    $data['step']=$condition['else_next_step'];  
                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                    $path=$st[0];
                                                                    $data['back']=$step['number']; 
                                                                    $data['next']=$path['next'];
                                                                    //echo '<pre>';print_r($path);exit;
                                                                }
                                                            }
                                                            else
                                                            {
                                                                if($result['value'] == $condition['value'])
                                                                {
                                                                    $data['step']=$condition['if_next_step'];
                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                    $path=$st[0];
                                                                    $data['back']=$step['number'];
                                                                    $data['next']=$path['next'];
                                                                }
                                                                else
                                                                {
                                                                    $data['step']=$condition['else_next_step'];  
                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                    $path=$st[0];
                                                                    $data['back']=$step['number']; 
                                                                    $data['next']=$path['next'];
                                                                    //echo '<pre>';print_r($path);exit;
                                                                }
                                                            } 
                                                        break;
                                                        case '<>':                   
                                                            if(isset($result[0]))
                                                             { 
                                                                if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                                                {
                                                                    $data['step']=$condition['if_next_step'];
                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                    $path=$st[0];
                                                                    $data['back']=$step['number'];
                                                                    $data['next']=$path['next'];
                                                                }
                                                                else
                                                                { //
                                                                    // echo '502 go';exit;
                                                                    $data['step']=$condition['else_next_step'];  
                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                    $path=$st[0];
                                                                    // print_r($path);
                                                                    $data['back']=$step['number']; 
                                                                    $data['next']=$path['next'];
                                                                }
                                                            }
                                                            else
                                                             { //
                                                                if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                                                {
                                                                    $data['step']=$condition['if_next_step'];
                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                    $path=$st[0];
                                                                    $data['back']=$step['number'];
                                                                    $data['next']=$path['next'];
                                                                }
                                                                else
                                                                { //
                                                                    // echo '502 go';exit;
                                                                    $data['step']=$condition['else_next_step'];  
                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                    $path=$st[0];
                                                                    // print_r($path);
                                                                    $data['back']=$step['number']; 
                                                                    $data['next']=$path['next'];
                                                                }
                                                            }
                                                        break;
                                                    }
                                                    // echo '<pre>';print_r($step);print_r($data);exit;
                                                    $step=$this->getStepByNumber($data['step'], $params['pathway']);
                                                    // echo "<script>console.log('3271. Next Step ".$step['number']." is ".$step['type'].")</script>";
                                                    if($step['type']=='question' || $step['type']=='info')
                                                    {
                                                        // echo 'next step q';exit;
                                                        $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                                        $data['question']=$st[0];
                                                        return $data;
                                                    }
                                                    if($step['type']=='condition')
                                                    {
                                                        $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                                                        $condition=$st[0];
                                                        // echo '1075 <pre>';print_r($condition);exit;
                                                        $d['step']=$condition['step_result'];
                                                        $d['pathway']=$params['pathway'];
                                                        $d['user_id']=$params['user_id'];

                                                        $result=$this->getStepAnswer($d);
                                                        // echo '271 <pre>';print_r($result);exit;

                                                        if($params['pathway']==3 && $d['step']==2)
                                                        {
                                                            $weight=$result[0]['value'];
                                                            $height=$result[1]['value'];
                                                            $result=array();
                                                            $t=$this->db->select('*')->from('step_answers')
                                                                        ->where('user_id', $params['user_id'])
                                                                        ->where('pathway', $params['pathway'])
                                                                        ->where('step', 2)
                                                                        ->get()
                                                                        ->result_array();
                                                            $result[0]['value']=$t[0]['value'];
                                                            // echo "<script>console.log('1989. Result is '".$result['value'].")</script>";

                                                        }
                                                        else
                                                        {
                                                            if(!$result)
                                                            {
                                                                $result=1;
                                                            }
                                                        }
                                                        switch($condition['operator'])
                                                        {
                                                            case '>':
                                                                if(isset($result[0]))
                                                                {
                                                                    if($result[0]['value'] > $condition['value'])
                                                                    {
                                                                        $data['step']=$condition['if_next_step'];
                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                        $path=$st[0];
                                                                        $data['back']=$step['number'];
                                                                        $data['next']=$path['next'];
                                                                    }
                                                                    else
                                                                    {
                                                                        $data['step']=$condition['else_next_step'];  
                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                        $path=$st[0];
                                                                        $data['back']=$step['number']; 
                                                                        $data['next']=$path['next'];
                                                                        //echo '<pre>';print_r($path);exit;
                                                                    }
                                                                }
                                                                else
                                                                {
                                                                    if($result['value'] > $condition['value'])
                                                                    {
                                                                        $data['step']=$condition['if_next_step'];
                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                        $path=$st[0];
                                                                        $data['back']=$step['number'];
                                                                        $data['next']=$path['next'];
                                                                    }
                                                                    else
                                                                    {
                                                                        $data['step']=$condition['else_next_step'];  
                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                        $path=$st[0];
                                                                        $data['back']=$step['number']; 
                                                                        $data['next']=$path['next'];
                                                                        //echo '<pre>';print_r($path);exit;
                                                                    }
                                                                } 
                                                                
                                                            break;
                                                            case '<':
                                                                if(isset($result[0]))
                                                                {
                                                                    if($result[0]['value'] < $condition['value'])
                                                                    {
                                                                        $data['step']=$condition['if_next_step'];
                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                        $path=$st[0];
                                                                        $data['back']=$step['number'];
                                                                        $data['next']=$path['next'];
                                                                    }
                                                                    else
                                                                    {
                                                                        $data['step']=$condition['else_next_step'];  
                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                        $path=$st[0];
                                                                        $data['back']=$step['number']; 
                                                                        $data['next']=$path['next'];
                                                                        //echo '<pre>';print_r($path);exit;
                                                                    }
                                                                }
                                                                else
                                                                {
                                                                    if($result['value'] < $condition['value'])
                                                                    {
                                                                        $data['step']=$condition['if_next_step'];
                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                        $path=$st[0];
                                                                        $data['back']=$step['number'];
                                                                        $data['next']=$path['next'];
                                                                    }
                                                                    else
                                                                    {
                                                                        $data['step']=$condition['else_next_step'];  
                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                        $path=$st[0];
                                                                        $data['back']=$step['number']; 
                                                                        $data['next']=$path['next'];
                                                                        //echo '<pre>';print_r($path);exit;
                                                                    }
                                                                } 
                                                            break;
                                                            case '==':
                                                            // echo 'result '.$result['value'];exit;
                                                                if(isset($result[0]))
                                                                {
                                                                    if($result[0]['value'] == $condition['value'])
                                                                    {
                                                                        $data['step']=$condition['if_next_step'];
                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                        $path=$st[0];
                                                                        $data['back']=$step['number'];
                                                                        $data['next']=$path['next'];
                                                                    }
                                                                    else
                                                                    {
                                                                        $data['step']=$condition['else_next_step'];  
                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                        $path=$st[0];
                                                                        $data['back']=$step['number']; 
                                                                        $data['next']=$path['next'];
                                                                        //echo '<pre>';print_r($path);exit;
                                                                    }
                                                                }
                                                                else
                                                                {
                                                                    if($result['value'] == $condition['value'])
                                                                    {
                                                                        $data['step']=$condition['if_next_step'];
                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                        $path=$st[0];
                                                                        $data['back']=$step['number'];
                                                                        $data['next']=$path['next'];
                                                                    }
                                                                    else
                                                                    {
                                                                        $data['step']=$condition['else_next_step'];  
                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                        $path=$st[0];
                                                                        $data['back']=$step['number']; 
                                                                        $data['next']=$path['next'];
                                                                        //echo '<pre>';print_r($path);exit;
                                                                    }
                                                                } 
                                                            break;
                                                            case '<>':                   
                                                                if(isset($result[0]))
                                                                 { 
                                                                    if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                                                    {
                                                                        $data['step']=$condition['if_next_step'];
                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                        $path=$st[0];
                                                                        $data['back']=$step['number'];
                                                                        $data['next']=$path['next'];
                                                                    }
                                                                    else
                                                                    { //
                                                                        // echo '502 go';exit;
                                                                        $data['step']=$condition['else_next_step'];  
                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                        $path=$st[0];
                                                                        // print_r($path);
                                                                        $data['back']=$step['number']; 
                                                                        $data['next']=$path['next'];
                                                                    }
                                                                }
                                                                else
                                                                 { //
                                                                    if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                                                    {
                                                                        $data['step']=$condition['if_next_step'];
                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                        $path=$st[0];
                                                                        $data['back']=$step['number'];
                                                                        $data['next']=$path['next'];
                                                                    }
                                                                    else
                                                                    { //
                                                                        // echo '502 go';exit;
                                                                        $data['step']=$condition['else_next_step'];  
                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                        $path=$st[0];
                                                                        // print_r($path);
                                                                        $data['back']=$step['number']; 
                                                                        $data['next']=$path['next'];
                                                                    }
                                                                }
                                                            break;
                                                        }
                                                        // echo '<pre>';print_r($step);print_r($data);exit;
                                                        $step=$this->getStepByNumber($data['step'], $params['pathway']);
                                                        // echo "<script>console.log('3490. Next Step ".$step['number']." is ".$step['type'].")</script>";
                                                        if($step['type']=='question' || $step['type']=='info')
                                                        {
                                                            // echo 'next step q';exit;
                                                            $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                                            $data['question']=$st[0];
                                                            return $data;
                                                        }
                                                        if($step['type']=='condition')
                                                        {
                                                            $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                                                            $condition=$st[0];
                                                            // echo '1075 <pre>';print_r($condition);exit;
                                                            $d['step']=$condition['step_result'];
                                                            $d['pathway']=$params['pathway'];
                                                            $d['user_id']=$params['user_id'];

                                                            $result=$this->getStepAnswer($d);
                                                            // echo '271 <pre>';print_r($result);exit;

                                                            if($params['pathway']==3 && $d['step']==2)
                                                            {
                                                                $weight=$result[0]['value'];
                                                                $height=$result[1]['value'];
                                                                $result=array();
                                                                $t=$this->db->select('*')->from('step_answers')
                                                                            ->where('user_id', $params['user_id'])
                                                                            ->where('pathway', $params['pathway'])
                                                                            ->where('step', 2)
                                                                            ->get()
                                                                            ->result_array();
                                                                $result[0]['value']=$t[0]['value'];
                                                                // echo "<script>console.log('1989. Result is '".$result['value'].")</script>";

                                                            }
                                                            else
                                                            {
                                                                if(!$result)
                                                                {
                                                                    $result=1;
                                                                }
                                                            }
                                                            switch($condition['operator'])
                                                            {
                                                                case '>':
                                                                    if(isset($result[0]))
                                                                    {
                                                                        if($result[0]['value'] > $condition['value'])
                                                                        {
                                                                            $data['step']=$condition['if_next_step'];
                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                            $path=$st[0];
                                                                            $data['back']=$step['number'];
                                                                            $data['next']=$path['next'];
                                                                        }
                                                                        else
                                                                        {
                                                                            $data['step']=$condition['else_next_step'];  
                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                            $path=$st[0];
                                                                            $data['back']=$step['number']; 
                                                                            $data['next']=$path['next'];
                                                                            //echo '<pre>';print_r($path);exit;
                                                                        }
                                                                    }
                                                                    else
                                                                    {
                                                                        if($result['value'] > $condition['value'])
                                                                        {
                                                                            $data['step']=$condition['if_next_step'];
                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                            $path=$st[0];
                                                                            $data['back']=$step['number'];
                                                                            $data['next']=$path['next'];
                                                                        }
                                                                        else
                                                                        {
                                                                            $data['step']=$condition['else_next_step'];  
                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                            $path=$st[0];
                                                                            $data['back']=$step['number']; 
                                                                            $data['next']=$path['next'];
                                                                            //echo '<pre>';print_r($path);exit;
                                                                        }
                                                                    } 
                                                                    
                                                                break;
                                                                case '<':
                                                                    if(isset($result[0]))
                                                                    {
                                                                        if($result[0]['value'] < $condition['value'])
                                                                        {
                                                                            $data['step']=$condition['if_next_step'];
                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                            $path=$st[0];
                                                                            $data['back']=$step['number'];
                                                                            $data['next']=$path['next'];
                                                                        }
                                                                        else
                                                                        {
                                                                            $data['step']=$condition['else_next_step'];  
                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                            $path=$st[0];
                                                                            $data['back']=$step['number']; 
                                                                            $data['next']=$path['next'];
                                                                            //echo '<pre>';print_r($path);exit;
                                                                        }
                                                                    }
                                                                    else
                                                                    {
                                                                        if($result['value'] < $condition['value'])
                                                                        {
                                                                            $data['step']=$condition['if_next_step'];
                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                            $path=$st[0];
                                                                            $data['back']=$step['number'];
                                                                            $data['next']=$path['next'];
                                                                        }
                                                                        else
                                                                        {
                                                                            $data['step']=$condition['else_next_step'];  
                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                            $path=$st[0];
                                                                            $data['back']=$step['number']; 
                                                                            $data['next']=$path['next'];
                                                                            //echo '<pre>';print_r($path);exit;
                                                                        }
                                                                    } 
                                                                break;
                                                                case '==':
                                                                // echo 'result '.$result['value'];exit;
                                                                    if(isset($result[0]))
                                                                    {
                                                                        if($result[0]['value'] == $condition['value'])
                                                                        {
                                                                            $data['step']=$condition['if_next_step'];
                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                            $path=$st[0];
                                                                            $data['back']=$step['number'];
                                                                            $data['next']=$path['next'];
                                                                        }
                                                                        else
                                                                        {
                                                                            $data['step']=$condition['else_next_step'];  
                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                            $path=$st[0];
                                                                            $data['back']=$step['number']; 
                                                                            $data['next']=$path['next'];
                                                                            //echo '<pre>';print_r($path);exit;
                                                                        }
                                                                    }
                                                                    else
                                                                    {
                                                                        if($result['value'] == $condition['value'])
                                                                        {
                                                                            $data['step']=$condition['if_next_step'];
                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                            $path=$st[0];
                                                                            $data['back']=$step['number'];
                                                                            $data['next']=$path['next'];
                                                                        }
                                                                        else
                                                                        {
                                                                            $data['step']=$condition['else_next_step'];  
                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                            $path=$st[0];
                                                                            $data['back']=$step['number']; 
                                                                            $data['next']=$path['next'];
                                                                            //echo '<pre>';print_r($path);exit;
                                                                        }
                                                                    } 
                                                                break;
                                                                case '<>':                   
                                                                    if(isset($result[0]))
                                                                     { 
                                                                        if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                                                        {
                                                                            $data['step']=$condition['if_next_step'];
                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                            $path=$st[0];
                                                                            $data['back']=$step['number'];
                                                                            $data['next']=$path['next'];
                                                                        }
                                                                        else
                                                                        { //
                                                                            // echo '502 go';exit;
                                                                            $data['step']=$condition['else_next_step'];  
                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                            $path=$st[0];
                                                                            // print_r($path);
                                                                            $data['back']=$step['number']; 
                                                                            $data['next']=$path['next'];
                                                                        }
                                                                    }
                                                                    else
                                                                     { //
                                                                        if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                                                        {
                                                                            $data['step']=$condition['if_next_step'];
                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                            $path=$st[0];
                                                                            $data['back']=$step['number'];
                                                                            $data['next']=$path['next'];
                                                                        }
                                                                        else
                                                                        { //
                                                                            // echo '502 go';exit;
                                                                            $data['step']=$condition['else_next_step'];  
                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                            $path=$st[0];
                                                                            // print_r($path);
                                                                            $data['back']=$step['number']; 
                                                                            $data['next']=$path['next'];
                                                                        }
                                                                    }
                                                                break;
                                                            }
                                                            // echo '<pre>';print_r($step);print_r($data);exit;
                                                            $step=$this->getStepByNumber($data['step'], $params['pathway']);
                                                            // echo '<pre>';print_r($step);print_r($data);exit;
                                                            // echo "<script>console.log('3709. Next Step ".$step['number']." is ".$step['type'].")</script>";
                                                            if($step['type']=='question' || $step['type']=='info')
                                                            {
                                                                // echo 'next step q';exit;
                                                                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                                                $data['question']=$st[0];
                                                                return $data;
                                                            }
                                                            if($step['type']=='condition')
                                                            {
                                                                $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                                                                $condition=$st[0];
                                                                // echo '1075 <pre>';print_r($condition);exit;
                                                                $d['step']=$condition['step_result'];
                                                                $d['pathway']=$params['pathway'];
                                                                $d['user_id']=$params['user_id'];

                                                                $result=$this->getStepAnswer($d);
                                                                // echo '271 <pre>';print_r($result);exit;

                                                                if($params['pathway']==3 && $d['step']==2)
                                                                {
                                                                    $weight=$result[0]['value'];
                                                                    $height=$result[1]['value'];
                                                                    $result=array();
                                                                    $t=$this->db->select('*')->from('step_answers')
                                                                                ->where('user_id', $params['user_id'])
                                                                                ->where('pathway', $params['pathway'])
                                                                                ->where('step', 2)
                                                                                ->get()
                                                                                ->result_array();
                                                                    $result[0]['value']=$t[0]['value'];
                                                                    // echo "<script>console.log('1989. Result is '".$result['value'].")</script>";

                                                                }
                                                                else
                                                                {
                                                                    if(!$result)
                                                                    {
                                                                        $result=1;
                                                                    }
                                                                }
                                                                switch($condition['operator'])
                                                                {
                                                                    case '>':
                                                                        if(isset($result[0]))
                                                                        {
                                                                            if($result[0]['value'] > $condition['value'])
                                                                            {
                                                                                $data['step']=$condition['if_next_step'];
                                                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                $path=$st[0];
                                                                                $data['back']=$step['number'];
                                                                                $data['next']=$path['next'];
                                                                            }
                                                                            else
                                                                            {
                                                                                $data['step']=$condition['else_next_step'];  
                                                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                $path=$st[0];
                                                                                $data['back']=$step['number']; 
                                                                                $data['next']=$path['next'];
                                                                                //echo '<pre>';print_r($path);exit;
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            if($result['value'] > $condition['value'])
                                                                            {
                                                                                $data['step']=$condition['if_next_step'];
                                                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                $path=$st[0];
                                                                                $data['back']=$step['number'];
                                                                                $data['next']=$path['next'];
                                                                            }
                                                                            else
                                                                            {
                                                                                $data['step']=$condition['else_next_step'];  
                                                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                $path=$st[0];
                                                                                $data['back']=$step['number']; 
                                                                                $data['next']=$path['next'];
                                                                                //echo '<pre>';print_r($path);exit;
                                                                            }
                                                                        } 
                                                                        
                                                                    break;
                                                                    case '<':
                                                                        if(isset($result[0]))
                                                                        {
                                                                            if($result[0]['value'] < $condition['value'])
                                                                            {
                                                                                $data['step']=$condition['if_next_step'];
                                                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                $path=$st[0];
                                                                                $data['back']=$step['number'];
                                                                                $data['next']=$path['next'];
                                                                            }
                                                                            else
                                                                            {
                                                                                $data['step']=$condition['else_next_step'];  
                                                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                $path=$st[0];
                                                                                $data['back']=$step['number']; 
                                                                                $data['next']=$path['next'];
                                                                                //echo '<pre>';print_r($path);exit;
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            if($result['value'] < $condition['value'])
                                                                            {
                                                                                $data['step']=$condition['if_next_step'];
                                                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                $path=$st[0];
                                                                                $data['back']=$step['number'];
                                                                                $data['next']=$path['next'];
                                                                            }
                                                                            else
                                                                            {
                                                                                $data['step']=$condition['else_next_step'];  
                                                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                $path=$st[0];
                                                                                $data['back']=$step['number']; 
                                                                                $data['next']=$path['next'];
                                                                                //echo '<pre>';print_r($path);exit;
                                                                            }
                                                                        } 
                                                                    break;
                                                                    case '==':
                                                                    // echo 'result '.$result['value'];exit;
                                                                        if(isset($result[0]))
                                                                        {
                                                                            if($result[0]['value'] == $condition['value'])
                                                                            {
                                                                                $data['step']=$condition['if_next_step'];
                                                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                $path=$st[0];
                                                                                $data['back']=$step['number'];
                                                                                $data['next']=$path['next'];
                                                                            }
                                                                            else
                                                                            {
                                                                                $data['step']=$condition['else_next_step'];  
                                                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                $path=$st[0];
                                                                                $data['back']=$step['number']; 
                                                                                $data['next']=$path['next'];
                                                                                //echo '<pre>';print_r($path);exit;
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            if($result['value'] == $condition['value'])
                                                                            {
                                                                                $data['step']=$condition['if_next_step'];
                                                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                $path=$st[0];
                                                                                $data['back']=$step['number'];
                                                                                $data['next']=$path['next'];
                                                                            }
                                                                            else
                                                                            {
                                                                                $data['step']=$condition['else_next_step'];  
                                                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                $path=$st[0];
                                                                                $data['back']=$step['number']; 
                                                                                $data['next']=$path['next'];
                                                                                //echo '<pre>';print_r($path);exit;
                                                                            }
                                                                        } 
                                                                    break;
                                                                    case '<>':                   
                                                                        if(isset($result[0]))
                                                                         { 
                                                                            if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                                                            {
                                                                                $data['step']=$condition['if_next_step'];
                                                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                $path=$st[0];
                                                                                $data['back']=$step['number'];
                                                                                $data['next']=$path['next'];
                                                                            }
                                                                            else
                                                                            { //
                                                                                // echo '502 go';exit;
                                                                                $data['step']=$condition['else_next_step'];  
                                                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                $path=$st[0];
                                                                                // print_r($path);
                                                                                $data['back']=$step['number']; 
                                                                                $data['next']=$path['next'];
                                                                            }
                                                                        }
                                                                        else
                                                                         { //
                                                                            if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                                                            {
                                                                                $data['step']=$condition['if_next_step'];
                                                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                $path=$st[0];
                                                                                $data['back']=$step['number'];
                                                                                $data['next']=$path['next'];
                                                                            }
                                                                            else
                                                                            { //
                                                                                // echo '502 go';exit;
                                                                                $data['step']=$condition['else_next_step'];  
                                                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                $path=$st[0];
                                                                                // print_r($path);
                                                                                $data['back']=$step['number']; 
                                                                                $data['next']=$path['next'];
                                                                            }
                                                                        }
                                                                    break;
                                                                }
                                                                // echo '<pre>';print_r($step);print_r($data);exit;
                                                                $step=$this->getStepByNumber($data['step'], $params['pathway']);
                                                                // echo '<pre>';print_r($step);print_r($data);exit;
                                                                // echo "<script>console.log('3930. Next Step ".$step['number']." is ".$step['type'].")</script>";
                                                                if($step['type']=='question' || $step['type']=='info')
                                                                {
                                                                    // echo 'next step q';exit;
                                                                    $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                                                    $data['question']=$st[0];
                                                                    return $data;
                                                                }
                                                                if($step['type']=='condition')
                                                                {
                                                                    $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                                                                    $condition=$st[0];
                                                                    // echo '1075 <pre>';print_r($condition);exit;
                                                                    $d['step']=$condition['step_result'];
                                                                    $d['pathway']=$params['pathway'];
                                                                    $d['user_id']=$params['user_id'];

                                                                    $result=$this->getStepAnswer($d);
                                                                    // echo '271 <pre>';print_r($result);exit;

                                                                    if($params['pathway']==3 && $d['step']==2)
                                                                    {
                                                                        $weight=$result[0]['value'];
                                                                        $height=$result[1]['value'];
                                                                        $result=array();
                                                                        $t=$this->db->select('*')->from('step_answers')
                                                                                    ->where('user_id', $params['user_id'])
                                                                                    ->where('pathway', $params['pathway'])
                                                                                    ->where('step', 2)
                                                                                    ->get()
                                                                                    ->result_array();
                                                                        $result[0]['value']=$t[0]['value'];
                                                                        // echo "<script>console.log('1989. Result is '".$result['value'].")</script>";

                                                                    }
                                                                    else
                                                                    {
                                                                        if(!$result)
                                                                        {
                                                                            $result=1;
                                                                        }
                                                                    }
                                                                    switch($condition['operator'])
                                                                    {
                                                                        case '>':
                                                                            if(isset($result[0]))
                                                                            {
                                                                                if($result[0]['value'] > $condition['value'])
                                                                                {
                                                                                    $data['step']=$condition['if_next_step'];
                                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                    $path=$st[0];
                                                                                    $data['back']=$step['number'];
                                                                                    $data['next']=$path['next'];
                                                                                }
                                                                                else
                                                                                {
                                                                                    $data['step']=$condition['else_next_step'];  
                                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                    $path=$st[0];
                                                                                    $data['back']=$step['number']; 
                                                                                    $data['next']=$path['next'];
                                                                                    //echo '<pre>';print_r($path);exit;
                                                                                }
                                                                            }
                                                                            else
                                                                            {
                                                                                if($result['value'] > $condition['value'])
                                                                                {
                                                                                    $data['step']=$condition['if_next_step'];
                                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                    $path=$st[0];
                                                                                    $data['back']=$step['number'];
                                                                                    $data['next']=$path['next'];
                                                                                }
                                                                                else
                                                                                {
                                                                                    $data['step']=$condition['else_next_step'];  
                                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                    $path=$st[0];
                                                                                    $data['back']=$step['number']; 
                                                                                    $data['next']=$path['next'];
                                                                                    //echo '<pre>';print_r($path);exit;
                                                                                }
                                                                            } 
                                                                            
                                                                        break;
                                                                        case '<':
                                                                            if(isset($result[0]))
                                                                            {
                                                                                if($result[0]['value'] < $condition['value'])
                                                                                {
                                                                                    $data['step']=$condition['if_next_step'];
                                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                    $path=$st[0];
                                                                                    $data['back']=$step['number'];
                                                                                    $data['next']=$path['next'];
                                                                                }
                                                                                else
                                                                                {
                                                                                    $data['step']=$condition['else_next_step'];  
                                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                    $path=$st[0];
                                                                                    $data['back']=$step['number']; 
                                                                                    $data['next']=$path['next'];
                                                                                    //echo '<pre>';print_r($path);exit;
                                                                                }
                                                                            }
                                                                            else
                                                                            {
                                                                                if($result['value'] < $condition['value'])
                                                                                {
                                                                                    $data['step']=$condition['if_next_step'];
                                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                    $path=$st[0];
                                                                                    $data['back']=$step['number'];
                                                                                    $data['next']=$path['next'];
                                                                                }
                                                                                else
                                                                                {
                                                                                    $data['step']=$condition['else_next_step'];  
                                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                    $path=$st[0];
                                                                                    $data['back']=$step['number']; 
                                                                                    $data['next']=$path['next'];
                                                                                    //echo '<pre>';print_r($path);exit;
                                                                                }
                                                                            } 
                                                                        break;
                                                                        case '==':
                                                                        // echo 'result '.$result['value'];exit;
                                                                            if(isset($result[0]))
                                                                            {
                                                                                if($result[0]['value'] == $condition['value'])
                                                                                {
                                                                                    $data['step']=$condition['if_next_step'];
                                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                    $path=$st[0];
                                                                                    $data['back']=$step['number'];
                                                                                    $data['next']=$path['next'];
                                                                                }
                                                                                else
                                                                                {
                                                                                    $data['step']=$condition['else_next_step'];  
                                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                    $path=$st[0];
                                                                                    $data['back']=$step['number']; 
                                                                                    $data['next']=$path['next'];
                                                                                    //echo '<pre>';print_r($path);exit;
                                                                                }
                                                                            }
                                                                            else
                                                                            {
                                                                                if($result['value'] == $condition['value'])
                                                                                {
                                                                                    $data['step']=$condition['if_next_step'];
                                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                    $path=$st[0];
                                                                                    $data['back']=$step['number'];
                                                                                    $data['next']=$path['next'];
                                                                                }
                                                                                else
                                                                                {
                                                                                    $data['step']=$condition['else_next_step'];  
                                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                    $path=$st[0];
                                                                                    $data['back']=$step['number']; 
                                                                                    $data['next']=$path['next'];
                                                                                    //echo '<pre>';print_r($path);exit;
                                                                                }
                                                                            } 
                                                                        break;
                                                                        case '<>':                   
                                                                            if(isset($result[0]))
                                                                             { 
                                                                                if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                                                                {
                                                                                    $data['step']=$condition['if_next_step'];
                                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                    $path=$st[0];
                                                                                    $data['back']=$step['number'];
                                                                                    $data['next']=$path['next'];
                                                                                }
                                                                                else
                                                                                { //
                                                                                    // echo '502 go';exit;
                                                                                    $data['step']=$condition['else_next_step'];  
                                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                    $path=$st[0];
                                                                                    // print_r($path);
                                                                                    $data['back']=$step['number']; 
                                                                                    $data['next']=$path['next'];
                                                                                }
                                                                            }
                                                                            else
                                                                             { //
                                                                                if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                                                                {
                                                                                    $data['step']=$condition['if_next_step'];
                                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                    $path=$st[0];
                                                                                    $data['back']=$step['number'];
                                                                                    $data['next']=$path['next'];
                                                                                }
                                                                                else
                                                                                { //
                                                                                    // echo '502 go';exit;
                                                                                    $data['step']=$condition['else_next_step'];  
                                                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                    $path=$st[0];
                                                                                    // print_r($path);
                                                                                    $data['back']=$step['number']; 
                                                                                    $data['next']=$path['next'];
                                                                                }
                                                                            }
                                                                        break;
                                                                    }
                                                                    // echo '<pre>';print_r($step);print_r($data);exit;
                                                                    $step=$this->getStepByNumber($data['step'], $params['pathway']);
                                                                    // echo '<pre>';print_r($step);print_r($data);exit;
                                                                    // echo "<script>console.log('4150. Next Step ".$step['number']." is ".$step['type'].")</script>";
                                                                    if($step['type']=='question' || $step['type']=='info')
                                                                    {
                                                                        // echo 'next step q';exit;
                                                                        $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                                                        $data['question']=$st[0];
                                                                        return $data;
                                                                    }
                                                                    if($step['type']=='condition')
                                                                    {
                                                                        $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                                                                        $condition=$st[0];
                                                                        // echo '1075 <pre>';print_r($condition);exit;
                                                                        $d['step']=$condition['step_result'];
                                                                        $d['pathway']=$params['pathway'];
                                                                        $d['user_id']=$params['user_id'];

                                                                        $result=$this->getStepAnswer($d);
                                                                        // echo '271 <pre>';print_r($result);exit;

                                                                        if($params['pathway']==3 && $d['step']==2)
                                                                        {
                                                                            $weight=$result[0]['value'];
                                                                            $height=$result[1]['value'];
                                                                            $result=array();
                                                                            $t=$this->db->select('*')->from('step_answers')
                                                                                        ->where('user_id', $params['user_id'])
                                                                                        ->where('pathway', $params['pathway'])
                                                                                        ->where('step', 2)
                                                                                        ->get()
                                                                                        ->result_array();
                                                                            $result[0]['value']=$t[0]['value'];
                                                                            // echo "<script>console.log('1989. Result is '".$result['value'].")</script>";

                                                                        }
                                                                        else
                                                                        {
                                                                            if(!$result)
                                                                            {
                                                                                $result=1;
                                                                            }
                                                                        }
                                                                        switch($condition['operator'])
                                                                        {
                                                                            case '>':
                                                                                if(isset($result[0]))
                                                                                {
                                                                                    if($result[0]['value'] > $condition['value'])
                                                                                    {
                                                                                        $data['step']=$condition['if_next_step'];
                                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                        $path=$st[0];
                                                                                        $data['back']=$step['number'];
                                                                                        $data['next']=$path['next'];
                                                                                    }
                                                                                    else
                                                                                    {
                                                                                        $data['step']=$condition['else_next_step'];  
                                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                        $path=$st[0];
                                                                                        $data['back']=$step['number']; 
                                                                                        $data['next']=$path['next'];
                                                                                        //echo '<pre>';print_r($path);exit;
                                                                                    }
                                                                                }
                                                                                else
                                                                                {
                                                                                    if($result['value'] > $condition['value'])
                                                                                    {
                                                                                        $data['step']=$condition['if_next_step'];
                                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                        $path=$st[0];
                                                                                        $data['back']=$step['number'];
                                                                                        $data['next']=$path['next'];
                                                                                    }
                                                                                    else
                                                                                    {
                                                                                        $data['step']=$condition['else_next_step'];  
                                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                        $path=$st[0];
                                                                                        $data['back']=$step['number']; 
                                                                                        $data['next']=$path['next'];
                                                                                        //echo '<pre>';print_r($path);exit;
                                                                                    }
                                                                                } 
                                                                                
                                                                            break;
                                                                            case '<':
                                                                                if(isset($result[0]))
                                                                                {
                                                                                    if($result[0]['value'] < $condition['value'])
                                                                                    {
                                                                                        $data['step']=$condition['if_next_step'];
                                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                        $path=$st[0];
                                                                                        $data['back']=$step['number'];
                                                                                        $data['next']=$path['next'];
                                                                                    }
                                                                                    else
                                                                                    {
                                                                                        $data['step']=$condition['else_next_step'];  
                                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                        $path=$st[0];
                                                                                        $data['back']=$step['number']; 
                                                                                        $data['next']=$path['next'];
                                                                                        //echo '<pre>';print_r($path);exit;
                                                                                    }
                                                                                }
                                                                                else
                                                                                {
                                                                                    if($result['value'] < $condition['value'])
                                                                                    {
                                                                                        $data['step']=$condition['if_next_step'];
                                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                        $path=$st[0];
                                                                                        $data['back']=$step['number'];
                                                                                        $data['next']=$path['next'];
                                                                                    }
                                                                                    else
                                                                                    {
                                                                                        $data['step']=$condition['else_next_step'];  
                                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                        $path=$st[0];
                                                                                        $data['back']=$step['number']; 
                                                                                        $data['next']=$path['next'];
                                                                                        //echo '<pre>';print_r($path);exit;
                                                                                    }
                                                                                } 
                                                                            break;
                                                                            case '==':
                                                                            // echo 'result '.$result['value'];exit;
                                                                                if(isset($result[0]))
                                                                                {
                                                                                    if($result[0]['value'] == $condition['value'])
                                                                                    {
                                                                                        $data['step']=$condition['if_next_step'];
                                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                        $path=$st[0];
                                                                                        $data['back']=$step['number'];
                                                                                        $data['next']=$path['next'];
                                                                                    }
                                                                                    else
                                                                                    {
                                                                                        $data['step']=$condition['else_next_step'];  
                                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                        $path=$st[0];
                                                                                        $data['back']=$step['number']; 
                                                                                        $data['next']=$path['next'];
                                                                                        //echo '<pre>';print_r($path);exit;
                                                                                    }
                                                                                }
                                                                                else
                                                                                {
                                                                                    if($result['value'] == $condition['value'])
                                                                                    {
                                                                                        $data['step']=$condition['if_next_step'];
                                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                        $path=$st[0];
                                                                                        $data['back']=$step['number'];
                                                                                        $data['next']=$path['next'];
                                                                                    }
                                                                                    else
                                                                                    {
                                                                                        $data['step']=$condition['else_next_step'];  
                                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                        $path=$st[0];
                                                                                        $data['back']=$step['number']; 
                                                                                        $data['next']=$path['next'];
                                                                                        //echo '<pre>';print_r($path);exit;
                                                                                    }
                                                                                } 
                                                                            break;
                                                                            case '<>':                   
                                                                                if(isset($result[0]))
                                                                                 { 
                                                                                    if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                                                                    {
                                                                                        $data['step']=$condition['if_next_step'];
                                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                        $path=$st[0];
                                                                                        $data['back']=$step['number'];
                                                                                        $data['next']=$path['next'];
                                                                                    }
                                                                                    else
                                                                                    { //
                                                                                        // echo '502 go';exit;
                                                                                        $data['step']=$condition['else_next_step'];  
                                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                        $path=$st[0];
                                                                                        // print_r($path);
                                                                                        $data['back']=$step['number']; 
                                                                                        $data['next']=$path['next'];
                                                                                    }
                                                                                }
                                                                                else
                                                                                 { //
                                                                                    if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                                                                    {
                                                                                        $data['step']=$condition['if_next_step'];
                                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                        $path=$st[0];
                                                                                        $data['back']=$step['number'];
                                                                                        $data['next']=$path['next'];
                                                                                    }
                                                                                    else
                                                                                    { //
                                                                                        // echo '502 go';exit;
                                                                                        $data['step']=$condition['else_next_step'];  
                                                                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                        $path=$st[0];
                                                                                        // print_r($path);
                                                                                        $data['back']=$step['number']; 
                                                                                        $data['next']=$path['next'];
                                                                                    }
                                                                                }
                                                                            break;
                                                                        }
                                                                        // echo '<pre>';print_r($step);print_r($data);exit;
                                                                        $step=$this->getStepByNumber($data['step'], $params['pathway']);
                                                                        // echo '<pre>';print_r($step);print_r($data);exit;
                                                                        // echo "<script>console.log('4370. Next Step ".$step['number']." is ".$step['type'].")</script>";
                                                                        if($step['type']=='question' || $step['type']=='info')
                                                                        {
                                                                            // echo 'next step q';exit;
                                                                            $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                                                            $data['question']=$st[0];
                                                                            return $data;
                                                                        }
                                                                        if($step['type']=='condition')
                                                                        {
                                                                            $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                                                                            $condition=$st[0];
                                                                            // echo '1075 <pre>';print_r($condition);exit;
                                                                            $d['step']=$condition['step_result'];
                                                                            $d['pathway']=$params['pathway'];
                                                                            $d['user_id']=$params['user_id'];

                                                                            $result=$this->getStepAnswer($d);
                                                                            // echo '271 <pre>';print_r($result);exit;

                                                                            if($params['pathway']==3 && $d['step']==2)
                                                                            {
                                                                                $weight=$result[0]['value'];
                                                                                $height=$result[1]['value'];
                                                                                $result=array();
                                                                                $t=$this->db->select('*')->from('step_answers')
                                                                                            ->where('user_id', $params['user_id'])
                                                                                            ->where('pathway', $params['pathway'])
                                                                                            ->where('step', 2)
                                                                                            ->get()
                                                                                            ->result_array();
                                                                                $result[0]['value']=$t[0]['value'];
                                                                                // echo "<script>console.log('1989. Result is '".$result['value'].")</script>";

                                                                            }
                                                                            else
                                                                            {
                                                                                if(!$result)
                                                                                {
                                                                                    $result=1;
                                                                                }
                                                                            }
                                                                            switch($condition['operator'])
                                                                            {
                                                                                case '>':
                                                                                    if(isset($result[0]))
                                                                                    {
                                                                                        if($result[0]['value'] > $condition['value'])
                                                                                        {
                                                                                            $data['step']=$condition['if_next_step'];
                                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                            $path=$st[0];
                                                                                            $data['back']=$step['number'];
                                                                                            $data['next']=$path['next'];
                                                                                        }
                                                                                        else
                                                                                        {
                                                                                            $data['step']=$condition['else_next_step'];  
                                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                            $path=$st[0];
                                                                                            $data['back']=$step['number']; 
                                                                                            $data['next']=$path['next'];
                                                                                            //echo '<pre>';print_r($path);exit;
                                                                                        }
                                                                                    }
                                                                                    else
                                                                                    {
                                                                                        if($result['value'] > $condition['value'])
                                                                                        {
                                                                                            $data['step']=$condition['if_next_step'];
                                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                            $path=$st[0];
                                                                                            $data['back']=$step['number'];
                                                                                            $data['next']=$path['next'];
                                                                                        }
                                                                                        else
                                                                                        {
                                                                                            $data['step']=$condition['else_next_step'];  
                                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                            $path=$st[0];
                                                                                            $data['back']=$step['number']; 
                                                                                            $data['next']=$path['next'];
                                                                                            //echo '<pre>';print_r($path);exit;
                                                                                        }
                                                                                    } 
                                                                                    
                                                                                break;
                                                                                case '<':
                                                                                    if(isset($result[0]))
                                                                                    {
                                                                                        if($result[0]['value'] < $condition['value'])
                                                                                        {
                                                                                            $data['step']=$condition['if_next_step'];
                                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                            $path=$st[0];
                                                                                            $data['back']=$step['number'];
                                                                                            $data['next']=$path['next'];
                                                                                        }
                                                                                        else
                                                                                        {
                                                                                            $data['step']=$condition['else_next_step'];  
                                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                            $path=$st[0];
                                                                                            $data['back']=$step['number']; 
                                                                                            $data['next']=$path['next'];
                                                                                            //echo '<pre>';print_r($path);exit;
                                                                                        }
                                                                                    }
                                                                                    else
                                                                                    {
                                                                                        if($result['value'] < $condition['value'])
                                                                                        {
                                                                                            $data['step']=$condition['if_next_step'];
                                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                            $path=$st[0];
                                                                                            $data['back']=$step['number'];
                                                                                            $data['next']=$path['next'];
                                                                                        }
                                                                                        else
                                                                                        {
                                                                                            $data['step']=$condition['else_next_step'];  
                                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                            $path=$st[0];
                                                                                            $data['back']=$step['number']; 
                                                                                            $data['next']=$path['next'];
                                                                                            //echo '<pre>';print_r($path);exit;
                                                                                        }
                                                                                    } 
                                                                                break;
                                                                                case '==':
                                                                                // echo 'result '.$result['value'];exit;
                                                                                    if(isset($result[0]))
                                                                                    {
                                                                                        if($result[0]['value'] == $condition['value'])
                                                                                        {
                                                                                            $data['step']=$condition['if_next_step'];
                                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                            $path=$st[0];
                                                                                            $data['back']=$step['number'];
                                                                                            $data['next']=$path['next'];
                                                                                        }
                                                                                        else
                                                                                        {
                                                                                            $data['step']=$condition['else_next_step'];  
                                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                            $path=$st[0];
                                                                                            $data['back']=$step['number']; 
                                                                                            $data['next']=$path['next'];
                                                                                            //echo '<pre>';print_r($path);exit;
                                                                                        }
                                                                                    }
                                                                                    else
                                                                                    {
                                                                                        if($result['value'] == $condition['value'])
                                                                                        {
                                                                                            $data['step']=$condition['if_next_step'];
                                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                            $path=$st[0];
                                                                                            $data['back']=$step['number'];
                                                                                            $data['next']=$path['next'];
                                                                                        }
                                                                                        else
                                                                                        {
                                                                                            $data['step']=$condition['else_next_step'];  
                                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                            $path=$st[0];
                                                                                            $data['back']=$step['number']; 
                                                                                            $data['next']=$path['next'];
                                                                                            //echo '<pre>';print_r($path);exit;
                                                                                        }
                                                                                    } 
                                                                                break;
                                                                                case '<>':                   
                                                                                    if(isset($result[0]))
                                                                                     { 
                                                                                        if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                                                                        {
                                                                                            $data['step']=$condition['if_next_step'];
                                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                            $path=$st[0];
                                                                                            $data['back']=$step['number'];
                                                                                            $data['next']=$path['next'];
                                                                                        }
                                                                                        else
                                                                                        { //
                                                                                            // echo '502 go';exit;
                                                                                            $data['step']=$condition['else_next_step'];  
                                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                            $path=$st[0];
                                                                                            // print_r($path);
                                                                                            $data['back']=$step['number']; 
                                                                                            $data['next']=$path['next'];
                                                                                        }
                                                                                    }
                                                                                    else
                                                                                     { //
                                                                                        if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                                                                        {
                                                                                            $data['step']=$condition['if_next_step'];
                                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                            $path=$st[0];
                                                                                            $data['back']=$step['number'];
                                                                                            $data['next']=$path['next'];
                                                                                        }
                                                                                        else
                                                                                        { //
                                                                                            // echo '502 go';exit;
                                                                                            $data['step']=$condition['else_next_step'];  
                                                                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                                                            $path=$st[0];
                                                                                            // print_r($path);
                                                                                            $data['back']=$step['number']; 
                                                                                            $data['next']=$path['next'];
                                                                                        }
                                                                                    }
                                                                                break;
                                                                            }
                                                                            // echo '<pre>';print_r($step);print_r($data);exit;
                                                                            $step=$this->getStepByNumber($data['step'], $params['pathway']);
                                                                            // echo '<pre>';print_r($step);print_r($data);exit;
                                                                            // echo "<script>console.log('4590. Next Step ".$step['number']." is ".$step['type'].")</script>";
                                                                            if($step['type']=='question' || $step['type']=='info')
                                                                            {
                                                                                // echo 'next step q';exit;
                                                                                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                                                                $data['question']=$st[0];
                                                                                return $data;
                                                                            }

                                                                            
                                                                        }

                                                                        
                                                                    }
                                                                    
                                                                }

                                                                
                                                            }

                                                            
                                                        }

                                                        
                                                    }
                                                    
                                                }
                                                
                                            }
                                            
                                        }
                                    }
                                }
                            }
                        }
                        else
                        {
                            $url = 'api/pw/next_pw/';
                            $myvars = http_build_query($data, '', '&');

                            $ch = curl_init( $url );
                            curl_setopt( $ch, CURLOPT_POST, 1);
                            curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
                            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
                            curl_setopt( $ch, CURLOPT_HEADER, 0);
                            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

                            curl_exec( $ch );
                        }
                            
                    }
                }
            }
            
        }      
        if($step['type']=='age')
        {
            //echo 'In age';exit;
            // echo "<script>console.log('2209 next step ".$step['number']." is age')</script>";
            $result=$params['age'];
            $st=$this->db->query('select * from step_age where step='.$step['id'])->result_array();
            $condition=$st[0];
            
            // echo '<pre>';print_r($condition);exit;
            switch($condition['operator'])
            {
                case '>':
                    if($result > $condition['value'])
                    {
                        $data['step']=$condition['if_next_step'];

                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['number'];
                        $data['next']=$path['next'];
                        
                    }
                    else
                    {
                        $data['step']=$condition['else_next_step'];  
                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['number']; 
                        $data['next']=$path['next'];
                        //echo '<pre>';print_r($path);exit;
                    }
                break;
                case '<':
                    if($result < $condition['value'])
                    {
                        $data['step']=$condition['if_next_step'];

                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['number'];
                        $data['next']=$path['next'];
                        
                    }
                    else
                    {
                        $data['step']=$condition['else_next_step'];  
                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['number']; 
                        $data['next']=$path['next'];
                        //echo '<pre>';print_r($path);exit;
                    }
                break;
                case '==':
                    if($result == $condition['value'])
                    {
                        $data['step']=$condition['if_next_step'];

                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['number'];
                        $data['next']=$path['next'];
                        
                    }
                    else
                    {
                        $data['step']=$condition['else_next_step'];  
                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['number']; 
                        $data['next']=$path['next'];
                        //echo '<pre>';print_r($path);exit;
                    }
                break;
                
            }
            
            $step=$this->getStepByNumber($data['step'], $params['pathway']);
            // echo "<script>console.log('2241 next step ".$step['number']." is ".$step['type']."')</script>";
            $data['step']=$step['number'];
            // echo '<pre>';print_r($step);print_r($data);exit;
            //$step=$this->getNextStep($step,$params);
            
            
            if($step['type']=='question' || $step['type']=='info')
            {
                // echo "<script>console.log('2250 next step is question')</script>";
                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                $data['question']=$st[0];
                return $data;
            }
            else
            {
                // echo "<script>console.log('2257 next step ".$data['step']." not question it is ".$step['type']."')</script>";
                //echo '<pre>';print_r($data);exit;
                $url = 'api/pw/next_pw/';
                $myvars = http_build_query($data, '', '&');

                $ch = curl_init( $url );
                curl_setopt( $ch, CURLOPT_POST, 1);
                curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

                curl_exec( $ch );
            }
        } 
        if($step['type']=='flag')
        {
            // echo "<script>console.log('2272 Step ".$step['number']." is flag')</script>";
            //$result=$params['score'];
            $st=$this->db->query('select * from step_flag where step='.$step['id'])->result_array();
            $condition=$st[0];
            
            // echo '<pre>';print_r($condition);exit;
            //$this->session->set_userdata('flag','red');
            $data['step']=$condition['if_next_step'];
            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
            $path=$st[0];
            // echo '<pre>';print_r($path);exit;
            $step=$this->getStepByNumber($path['step'], $params['pathway']);
            if($step['type']=='question' || $step['type']=='info')
            {
                // echo "<script>console.log('2286 Next Step ".$step['number']." is ".$step['type']." ')</script>";
                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                $data['question']=$st[0];
                $data['back']=$path['back'];
                $data['step']=$step['number'];
                $data['next']=$path['next'];
                return $data;
            }
            $st=$this->db->query('select * from pathflow where step='. $step['number'].' and pathway='.$params['pathway'])->result_array();
            $path=$st[0];
            $data['back']=$path['back'];
            $data['step']=$step['number'];
            $data['next']=$path['next'];

            // echo "<script>console.log('2300 Next Step ".$step['number']." is ".$step['type']." ')</script>";
            // echo '<pre>';print_r($path);print_r($data);print_r($step);exit;
            if($step['type']=='question' || $step['type']=='info')
            {
                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                $data['question']=$st[0];
                return $data;
            }
            else
            {
               // echo "<script>console.log('2310 Next Step ".$step['number']." is ".$step['type']." ')</script>";
               if($step['type']=='condition')
               {
                    $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                    $condition=$st[0];                    
                    $d=array();
                    $d['step']=$condition['step_result'];
                    $d['pathway']=$params['pathway'];
                    $d['user_id']=$params['user_id'];

                    $result=$this->getStepAnswer($d);
                    // echo '<pre>';print_r($condition);exit;
                    //print_r($step['id'].'-'.$params['pathway']); exit;
                    // echo "<script>>console.log('1158 result is ".$result['value']."')</script>";
                    switch($condition['operator'])
                    {
                        case '>':
                            if(isset($result[0]))
                            {
                                if($result[0]['value'] > $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            }
                            else
                            {
                                if($result['value'] > $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            } 
                            
                        break;
                        case '<':
                            if(isset($result[0]))
                            {
                                if($result[0]['value'] < $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            }
                            else
                            {
                                if($result['value'] < $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            } 
                        break;
                        case '==':
                        // echo 'result '.$result['value'];exit;
                            if(isset($result[0]))
                            {
                                if($result[0]['value'] == $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            }
                            else
                            {
                                if($result['value'] == $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            } 
                        break;
                        case '<>':                   
                            if(isset($result[0]))
                             { 
                                if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                { //
                                    // echo '502 go';exit;
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    // print_r($path);
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                }
                            }
                            else
                             { //
                                if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                { //
                                    // echo '502 go';exit;
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    // print_r($path);
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                }
                            }
                        break;
                    }
                    //echo '<pre>';print_r($step);print_r($data);exit;
                    $step=$this->getStepByNumber($data['step'], $params['pathway']);
                    //$step=$this->getNextStep($step,$params);
                    //echo '<pre>';print_r($step);print_r($data);exit;

                    if($step['type']=='question' || $step['type']=='info')
                    {
                        // echo "<script>>console.log('1340 step ".$step['id']." is question')</script>";
                        $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                        $data['question']=$st[0];
                        return $data;
                    }
                    else
                    {   
                        if($step['type']=='age')
                        {
                            //echo 'In age';exit;
                            // echo "<script>console.log('1350 Next Step ".$step['number']." is age')</script>";
                            $result=$params['age'];
                            $st=$this->db->query('select * from step_age where step='.$step['id'])->result_array();
                            $condition=$st[0];
                            
                            // echo '<pre>';print_r($condition);exit;
                            switch($condition['operator'])
                            {
                                case '>':
                                    if($result > $condition['value'])
                                    {
                                        $data['step']=$condition['if_next_step'];

                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        $data['back']=$step['number'];
                                        $data['next']=$path['next'];
                                        
                                    }
                                    else
                                    {
                                        $data['step']=$condition['else_next_step'];  
                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        $data['back']=$step['number']; 
                                        $data['next']=$path['next'];
                                        //echo '<pre>';print_r($path);exit;
                                    }
                                break;
                                
                            }
                            
                            $step=$this->getStepByNumber($data['step'], $params['pathway']);
                            // echo "<script>console.log('1383 Next Step ".$step['number']." is ".$step['type']."')</script>";
                            $data['step']=$step['id'];
                            // echo '<pre>';print_r($step);print_r($data);exit;
                            //$step=$this->getNextStep($step,$params);
                            
                            
                            if($step['type']=='question' || $step['type']=='info')
                            {
                                // echo "<script>>console.log('1391 next step is question')</script>";
                                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                $data['question']=$st[0];
                                return $data;
                            }
                            else
                            {
                                $st=$this->db->query('select * from step_flag where step='.$step['id'])->result_array();
                                $condition=$st[0];
                                
                                // echo '<pre>';print_r($condition);exit;
                                $data['step']=$condition['if_next_step'];
                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                $path=$st[0];
                                $step=$this->getStepByNumber($condition['if_next_step'], $params['pathway']);

                                $data['back']=$path['back'];
                                $data['step']=$step['number'];
                                $data['next']=$path['next'];
                                // print_r($data);
                                // echo "<script>>console.log('1411 Next Step ".$step['number']." is ".$step['type']." ')</script>";
                                // echo '<pre>';print_r($path);print_r($data);print_r($step);exit;
                                if($step['type']=='question' || $step['type']=='info')
                                {
                                    $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                    $data['question']=$st[0];
                                    return $data;
                                }
                                else
                                {
                                    $url = 'api/pw/next_pw/';
                                    $myvars = http_build_query($data, '', '&');

                                    $ch = curl_init( $url );
                                    curl_setopt( $ch, CURLOPT_POST, 1);
                                    curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
                                    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

                                    curl_exec( $ch );
                                }
                            }
                        } 
                        else
                        {
                            $url = 'api/pw/next_pw/';
                            $myvars = http_build_query($data, '', '&');

                            $ch = curl_init( $url );
                            curl_setopt( $ch, CURLOPT_POST, 1);
                            curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
                            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
                            curl_setopt( $ch, CURLOPT_HEADER, 0);
                            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

                            curl_exec( $ch );
                        }
                        
                    }
               }
               else
                {   
                    // echo "<script>console.log('1452 Next Step ".$step['number']." not question')</script>";
                    //echo '<pre>';print_r($data);exit;
                    $url = 'api/pw/next_pw/';
                    $myvars = http_build_query($data, '', '&');

                    $ch = curl_init( $url );
                    curl_setopt( $ch, CURLOPT_POST, 1);
                    curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
                    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
                    curl_setopt( $ch, CURLOPT_HEADER, 0);
                    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

                    curl_exec( $ch );
                }
            }
        }
        if($step['type']=='gender')
        {
            //echo 'In age';exit;
            // echo "<script>console.log('1471 next step ".$step['number']." is gender')</script>";
            $result=$params['gender'];
            $st=$this->db->query('select * from step_gender where step='.$step['id'])->result_array();
            $condition=$st[0];
            
            // echo '<pre>';print_r($condition);exit;
            switch($condition['operator'])
            {
                case '>':
                    if($result > $condition['value'])
                    {
                        $data['step']=$condition['if_next_step'];

                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['id'];
                        $data['next']=$path['next'];
                    }
                    else
                    {
                        $data['step']=$condition['else_next_step'];  
                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['id']; 
                        $data['next']=$path['next'];
                    }
                break;
                case '==':
                    if($result == $condition['value'])
                    {
                        $data['step']=$condition['if_next_step'];

                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['id'];
                        $data['next']=$path['next'];
                    }
                    else
                    {
                        $data['step']=$condition['else_next_step'];  
                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['number']; 
                        $data['next']=$path['next'];
                    }
                break;
                
            }
            
            $step=$this->getStepByNumber($data['step'], $params['pathway']);
            // echo "<script>console.log('1860 next step ".$step['number']." is ".$step['type']."')</script>";
            $data['step']=$step['number'];
            // echo '<pre>';print_r($step);print_r($data);exit;
            //$step=$this->getNextStep($step,$params);
            
            
            if($step['type']=='question' || $step['type']=='info')
            {
                // echo "<script>console.log('5229 next step is question')</script>";
                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                $data['question']=$st[0];
                return $data;
            }
            else
            {
                // echo "<script>console.log('1875 next step ".$step['number']." not question it is ".$step['type']."')</script>";
                if($step['type']=='gender')
                {
                    //echo 'In age';exit;
                    $result=$params['gender'];
                    $st=$this->db->query('select * from step_gender where step='.$step['id'])->result_array();
                    $condition=$st[0];
                    
                    // echo '<pre>';print_r($condition);exit;
                    switch($condition['operator'])
                    {
                        case '==':
                            if($result > $condition['value'])
                            {
                                $data['step']=$condition['if_next_step'];

                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                $path=$st[0];
                                $data['back']=$step['id'];
                                $data['next']=$path['next'];
                            }
                            else
                            {
                                $data['step']=$condition['else_next_step'];  
                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                $path=$st[0];
                                $data['back']=$step['id']; 
                                $data['next']=$path['next'];
                            }
                        break;
                        
                    }
                    
                    $step=$this->getStepByNumber($data['step'], $params['pathway']);
                    // echo "<script>console.log('1890 next step ".$step['number']." is ".$step['type']."')</script>";
                    $data['step']=$step['id'];
                    // echo '<pre>';print_r($step);print_r($data);exit;
                    //$step=$this->getNextStep($step,$params);
                    
                    
                    if($step['type']=='question' || $step['type']=='info')
                    {
                        // echo "<script>console.log('1898 next step is question')</script>";
                        $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                        $data['question']=$st[0];
                        return $data;
                    }
                    else
                    {
                        if($step['type']=='condition')
                        {
                            $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                            $condition=$st[0];                    
                            $d=array();
                            $d['step']=$condition['step_result'];
                            $d['pathway']=$params['pathway'];
                            $d['user_id']=$params['user_id'];

                            $result=$this->getStepAnswer($d);
                            // echo '<pre>';print_r($condition);exit;
                            //print_r($step['id'].'-'.$params['pathway']); exit;
                            // echo "<script>>console.log('1936 result is ".$result['value']."')</script>";
                            switch($condition['operator'])
                            {
                                case '>':
                                    if(isset($result[0]))
                                    {
                                        if($result[0]['value'] > $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    }
                                    else
                                    {
                                        if($result['value'] > $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    } 
                                    
                                break;
                                case '<':
                                    if(isset($result[0]))
                                    {
                                        if($result[0]['value'] < $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    }
                                    else
                                    {
                                        if($result['value'] < $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    } 
                                break;
                                case '==':
                                // echo 'result '.$result['value'];exit;
                                    if(isset($result[0]))
                                    {
                                        if($result[0]['value'] == $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    }
                                    else
                                    {
                                        if($result['value'] == $condition['value'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        {
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                            //echo '<pre>';print_r($path);exit;
                                        }
                                    } 
                                break;
                                case '<>':                   
                                    if(isset($result[0]))
                                     { 
                                        if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        { //
                                            // echo '502 go';exit;
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            // print_r($path);
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                        }
                                    }
                                    else
                                     { //
                                        if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                        {
                                            $data['step']=$condition['if_next_step'];
                                            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            $data['back']=$step['number'];
                                            $data['next']=$path['next'];
                                        }
                                        else
                                        { //
                                            // echo '502 go';exit;
                                            $data['step']=$condition['else_next_step'];  
                                            $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                            $path=$st[0];
                                            // print_r($path);
                                            $data['back']=$step['number']; 
                                            $data['next']=$path['next'];
                                        }
                                    }
                                break;
                            }
                            //echo '<pre>';print_r($step);print_r($data);exit;
                            $step=$this->getStepByNumber($data['step'], $params['pathway']);
                            //$step=$this->getNextStep($step,$params);
                            //echo '<pre>';print_r($step);print_r($data);exit;
                            // echo "<script>>console.log('2117 step ".$step['number']." is ".$step['type']."')</script>";
                            if($step['type']=='question' || $step['type']=='info')
                            {
                                
                                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                $data['question']=$st[0];
                                return $data;
                            }
                            elseif($step['type']=='condition')
                            {
                                $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                                $condition=$st[0];                    
                                $d=array();
                                $d['step']=$condition['step_result'];
                                $d['pathway']=$params['pathway'];
                                $d['user_id']=$params['user_id'];

                                $result=$this->getStepAnswer($d);
                                // echo '<pre>';print_r($result);exit;
                                //print_r($step['id'].'-'.$params['pathway']); exit;
                                // echo "<script>>console.log('2136 result is ".$result['value']."')</script>";
                                switch($condition['operator'])
                                {
                                    case '>':
                                        if(isset($result[0]))
                                        {
                                            if($result[0]['value'] > $condition['value'])
                                            {
                                                $data['step']=$condition['if_next_step'];
                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number'];
                                                $data['next']=$path['next'];
                                            }
                                            else
                                            {
                                                $data['step']=$condition['else_next_step'];  
                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number']; 
                                                $data['next']=$path['next'];
                                                //echo '<pre>';print_r($path);exit;
                                            }
                                        }
                                        else
                                        {
                                            if($result['value'] > $condition['value'])
                                            {
                                                $data['step']=$condition['if_next_step'];
                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number'];
                                                $data['next']=$path['next'];
                                            }
                                            else
                                            {
                                                $data['step']=$condition['else_next_step'];  
                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number']; 
                                                $data['next']=$path['next'];
                                                //echo '<pre>';print_r($path);exit;
                                            }
                                        } 
                                        
                                    break;
                                    case '<':
                                        if(isset($result[0]))
                                        {
                                            if($result[0]['value'] < $condition['value'])
                                            {
                                                $data['step']=$condition['if_next_step'];
                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number'];
                                                $data['next']=$path['next'];
                                            }
                                            else
                                            {
                                                $data['step']=$condition['else_next_step'];  
                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number']; 
                                                $data['next']=$path['next'];
                                                //echo '<pre>';print_r($path);exit;
                                            }
                                        }
                                        else
                                        {
                                            if($result['value'] < $condition['value'])
                                            {
                                                $data['step']=$condition['if_next_step'];
                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number'];
                                                $data['next']=$path['next'];
                                            }
                                            else
                                            {
                                                $data['step']=$condition['else_next_step'];  
                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number']; 
                                                $data['next']=$path['next'];
                                                //echo '<pre>';print_r($path);exit;
                                            }
                                        } 
                                    break;
                                    case '==':
                                    // echo 'result '.$result['value'];exit;
                                        if(isset($result[0]))
                                        {
                                            if($result[0]['value'] == $condition['value'])
                                            {
                                                $data['step']=$condition['if_next_step'];
                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number'];
                                                $data['next']=$path['next'];
                                            }
                                            else
                                            {
                                                $data['step']=$condition['else_next_step'];  
                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number']; 
                                                $data['next']=$path['next'];
                                                //echo '<pre>';print_r($path);exit;
                                            }
                                        }
                                        else
                                        {
                                            if($result['value'] == $condition['value'])
                                            {
                                                $data['step']=$condition['if_next_step'];
                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number'];
                                                $data['next']=$path['next'];
                                            }
                                            else
                                            {
                                                $data['step']=$condition['else_next_step'];  
                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number']; 
                                                $data['next']=$path['next'];
                                                //echo '<pre>';print_r($path);exit;
                                            }
                                        } 
                                    break;
                                    case '<>':                   
                                        if(isset($result[0]))
                                         { 
                                            if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                            {
                                                $data['step']=$condition['if_next_step'];
                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number'];
                                                $data['next']=$path['next'];
                                            }
                                            else
                                            { //
                                                // echo '502 go';exit;
                                                $data['step']=$condition['else_next_step'];  
                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                // print_r($path);
                                                $data['back']=$step['number']; 
                                                $data['next']=$path['next'];
                                            }
                                        }
                                        else
                                         { //
                                            if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                            {
                                                $data['step']=$condition['if_next_step'];
                                                $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                $data['back']=$step['number'];
                                                $data['next']=$path['next'];
                                            }
                                            else
                                            { //
                                                // echo '502 go';exit;
                                                $data['step']=$condition['else_next_step'];  
                                                $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                $path=$st[0];
                                                // print_r($path);
                                                $data['back']=$step['number']; 
                                                $data['next']=$path['next'];
                                            }
                                        }
                                    break;
                                }
                                //echo '<pre>';print_r($step);print_r($data);exit;
                                $step=$this->getStepByNumber($data['step'], $params['pathway']);
                                //$step=$this->getNextStep($step,$params);
                                //echo '<pre>';print_r($step);print_r($data);exit;
                                // echo "<script>>console.log('2316 step ".$step['number']." is ".$step['type']."')</script>";
                                if($step['type']=='question' || $step['type']=='info')
                                {
                                    
                                    $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                    $data['question']=$st[0];
                                    return $data;
                                }
                                elseif($step['type']=='condition')
                                {
                                    $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                                    $condition=$st[0];                    
                                    $d=array();
                                    $d['step']=$condition['step_result'];
                                    $d['pathway']=$params['pathway'];
                                    $d['user_id']=$params['user_id'];

                                    $result=$this->getStepAnswer($d);
                                    // echo '<pre>';print_r($condition);exit;
                                    //print_r($step['id'].'-'.$params['pathway']); exit;
                                    // echo "<script>>console.log('2336 result is ".$result['value']."')</script>";
                                    switch($condition['operator'])
                                    {
                                        case '>':
                                            if(isset($result[0]))
                                            {
                                                if($result[0]['value'] > $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            }
                                            else
                                            {
                                                if($result['value'] > $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            } 
                                            
                                        break;
                                        case '<':
                                            if(isset($result[0]))
                                            {
                                                if($result[0]['value'] < $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            }
                                            else
                                            {
                                                if($result['value'] < $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            } 
                                        break;
                                        case '==':
                                        // echo 'result '.$result['value'];exit;
                                            if(isset($result[0]))
                                            {
                                                if($result[0]['value'] == $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            }
                                            else
                                            {
                                                if($result['value'] == $condition['value'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                {
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                    //echo '<pre>';print_r($path);exit;
                                                }
                                            } 
                                        break;
                                        case '<>':                   
                                            if(isset($result[0]))
                                             { 
                                                if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                { //
                                                    // echo '502 go';exit;
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    // print_r($path);
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                }
                                            }
                                            else
                                             { //
                                                if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                                {
                                                    $data['step']=$condition['if_next_step'];
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    $data['back']=$step['number'];
                                                    $data['next']=$path['next'];
                                                }
                                                else
                                                { //
                                                    // echo '502 go';exit;
                                                    $data['step']=$condition['else_next_step'];  
                                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                                    $path=$st[0];
                                                    // print_r($path);
                                                    $data['back']=$step['number']; 
                                                    $data['next']=$path['next'];
                                                }
                                            }
                                        break;
                                    }
                                    //echo '<pre>';print_r($step);print_r($data);exit;
                                    $step=$this->getStepByNumber($data['step'], $params['pathway']);
                                    //$step=$this->getNextStep($step,$params);
                                    //echo '<pre>';print_r($step);print_r($data);exit;
                                    // echo "<script>>console.log('2516 step ".$step['number']." is ".$step['type']."')</script>";
                                    if($step['type']=='question' || $step['type']=='info')
                                    {
                                        
                                        $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                                        $data['question']=$st[0];
                                        return $data;
                                    }
                                }
                            }
                        }
                    }
                }
                if($step['type']=='condition')
                {
                    $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                    $condition=$st[0];                    
                    $d=array();
                    $d['step']=$condition['step_result'];
                    $d['pathway']=$params['pathway'];
                    $d['user_id']=$params['user_id'];

                    $result=$this->getStepAnswer($d);
                    // echo '<pre>';print_r($result);exit;
                    //print_r($step['id'].'-'.$params['pathway']); exit;
                    // echo "<script>>console.log('2541 result is ".$result['value']."')</script>";
                    switch($condition['operator'])
                    {
                        case '>':
                            if(isset($result[0]))
                            {
                                if($result[0]['value'] > $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            }
                            else
                            {
                                if($result['value'] > $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            } 
                            
                        break;
                        case '<':
                            if(isset($result[0]))
                            {
                                if($result[0]['value'] < $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            }
                            else
                            {
                                if($result['value'] < $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            } 
                        break;
                        case '==':
                        // echo 'result '.$result['value'];exit;
                            if(isset($result[0]))
                            {
                                if($result[0]['value'] == $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            }
                            else
                            {
                                if($result['value'] == $condition['value'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                {
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                    //echo '<pre>';print_r($path);exit;
                                }
                            } 
                        break;
                        case '<>':                   
                            if(isset($result[0]))
                             { 
                                if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                { //
                                    // echo '502 go';exit;
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    // print_r($path);
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                }
                            }
                            else
                             { //
                                if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                {
                                    $data['step']=$condition['if_next_step'];
                                    $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    $data['back']=$step['number'];
                                    $data['next']=$path['next'];
                                }
                                else
                                { //
                                    // echo '502 go';exit;
                                    $data['step']=$condition['else_next_step'];  
                                    $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                    $path=$st[0];
                                    // print_r($path);
                                    $data['back']=$step['number']; 
                                    $data['next']=$path['next'];
                                }
                            }
                        break;
                    }
                    //echo '<pre>';print_r($step);print_r($data);exit;
                    $step=$this->getStepByNumber($data['step'], $params['pathway']);
                    //$step=$this->getNextStep($step,$params);
                    //echo '<pre>';print_r($step);print_r($data);exit;
                    // echo "<script>>console.log('2721 step ".$step['number']." is ".$step['type']."')</script>";
                    if($step['type']=='question' || $step['type']=='info')
                    {
                        
                        $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                        $data['question']=$st[0];
                        return $data;
                    }
                    if($step['type']=='condition')
                    {
                        $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
                        $condition=$st[0];                    
                        $d=array();
                        $d['step']=$condition['step_result'];
                        $d['pathway']=$params['pathway'];
                        $d['user_id']=$params['user_id'];

                        $result=$this->getStepAnswer($d);
                        // echo '<pre>';print_r($condition);exit;
                        //print_r($step['id'].'-'.$params['pathway']); exit;
                        // echo "<script>>console.log('2741 result is ".$result['value']."')</script>";
                        switch($condition['operator'])
                        {
                            case '>':
                                if(isset($result[0]))
                                {
                                    if($result[0]['value'] > $condition['value'])
                                    {
                                        $data['step']=$condition['if_next_step'];
                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        $data['back']=$step['number'];
                                        $data['next']=$path['next'];
                                    }
                                    else
                                    {
                                        $data['step']=$condition['else_next_step'];  
                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        $data['back']=$step['number']; 
                                        $data['next']=$path['next'];
                                        //echo '<pre>';print_r($path);exit;
                                    }
                                }
                                else
                                {
                                    if($result['value'] > $condition['value'])
                                    {
                                        $data['step']=$condition['if_next_step'];
                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        $data['back']=$step['number'];
                                        $data['next']=$path['next'];
                                    }
                                    else
                                    {
                                        $data['step']=$condition['else_next_step'];  
                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        $data['back']=$step['number']; 
                                        $data['next']=$path['next'];
                                        //echo '<pre>';print_r($path);exit;
                                    }
                                } 
                                
                            break;
                            case '<':
                                if(isset($result[0]))
                                {
                                    if($result[0]['value'] < $condition['value'])
                                    {
                                        $data['step']=$condition['if_next_step'];
                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        $data['back']=$step['number'];
                                        $data['next']=$path['next'];
                                    }
                                    else
                                    {
                                        $data['step']=$condition['else_next_step'];  
                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        $data['back']=$step['number']; 
                                        $data['next']=$path['next'];
                                        //echo '<pre>';print_r($path);exit;
                                    }
                                }
                                else
                                {
                                    if($result['value'] < $condition['value'])
                                    {
                                        $data['step']=$condition['if_next_step'];
                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        $data['back']=$step['number'];
                                        $data['next']=$path['next'];
                                    }
                                    else
                                    {
                                        $data['step']=$condition['else_next_step'];  
                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        $data['back']=$step['number']; 
                                        $data['next']=$path['next'];
                                        //echo '<pre>';print_r($path);exit;
                                    }
                                } 
                            break;
                            case '==':
                            // echo 'result '.$result['value'];exit;
                                if(isset($result[0]))
                                {
                                    if($result[0]['value'] == $condition['value'])
                                    {
                                        $data['step']=$condition['if_next_step'];
                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        $data['back']=$step['number'];
                                        $data['next']=$path['next'];
                                    }
                                    else
                                    {
                                        $data['step']=$condition['else_next_step'];  
                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        $data['back']=$step['number']; 
                                        $data['next']=$path['next'];
                                        //echo '<pre>';print_r($path);exit;
                                    }
                                }
                                else
                                {
                                    if($result['value'] == $condition['value'])
                                    {
                                        $data['step']=$condition['if_next_step'];
                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        $data['back']=$step['number'];
                                        $data['next']=$path['next'];
                                    }
                                    else
                                    {
                                        $data['step']=$condition['else_next_step'];  
                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        $data['back']=$step['number']; 
                                        $data['next']=$path['next'];
                                        //echo '<pre>';print_r($path);exit;
                                    }
                                } 
                            break;
                            case '<>':                   
                                if(isset($result[0]))
                                 { 
                                    if($result[0]['value'] >= $condition['value_from'] && $result[0]['value'] <= $condition['value_to'])
                                    {
                                        $data['step']=$condition['if_next_step'];
                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        $data['back']=$step['number'];
                                        $data['next']=$path['next'];
                                    }
                                    else
                                    { //
                                        // echo '502 go';exit;
                                        $data['step']=$condition['else_next_step'];  
                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        // print_r($path);
                                        $data['back']=$step['number']; 
                                        $data['next']=$path['next'];
                                    }
                                }
                                else
                                 { //
                                    if($result['value'] >= $condition['value_from'] && $result['value'] <= $condition['value_to'])
                                    {
                                        $data['step']=$condition['if_next_step'];
                                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        $data['back']=$step['number'];
                                        $data['next']=$path['next'];
                                    }
                                    else
                                    { //
                                        // echo '502 go';exit;
                                        $data['step']=$condition['else_next_step'];  
                                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'].' and pathway='.$params['pathway'])->result_array();
                                        $path=$st[0];
                                        // print_r($path);
                                        $data['back']=$step['number']; 
                                        $data['next']=$path['next'];
                                    }
                                }
                            break;
                        }
                        //echo '<pre>';print_r($step);print_r($data);exit;
                        $step=$this->getStepByNumber($data['step'], $params['pathway']);
                        //$step=$this->getNextStep($step,$params);
                        //echo '<pre>';print_r($step);print_r($data);exit;
                        // echo "<script>>console.log('2921 step ".$step['number']." is ".$step['type']."')</script>";
                        if($step['type']=='question' || $step['type']=='info')
                        {
                            
                            $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                            $data['question']=$st[0];
                            return $data;
                        }
                    }
                }
            }
        }
        if($step['type']=='formula')
        {
            // echo 'In Formula';exit;
            $st=$this->db->select('*')->from('step_answers')
                ->where('pathway',$params['pathway'])
                ->where('step', $step['number']-1)
                ->where('user_id',$params['user_id'])
                ->where('field_name','weight')
                ->get()
                ->result_array();
            // echo $this->db->last_query();
            // print_r($st);exit;
            $weight=$st[0]['value'];
            $st=$this->db->select('*')->from('step_answers')
                ->where('pathway',$params['pathway'])
                ->where('user_id',$params['user_id'])
                ->where('step', $step['number']-1)
                ->where('field_name','height')
                ->get()
                ->result_array();
            $height=$st[0]['value'];
            $result=(($weight)/(($height*$height)/10000));
            $category='not set';
            if($result<15)
            {
                $category='very severely underweight';
            }
            elseif($result >=15 && $result <=15.99)
            {
                $category='severely underweight';
            }     
            elseif($result >=16 && $result <=18.50)
            {
                $category='underweight';
            } 
            elseif($result >= 18.50 && $result <=25)
            {
                $category='normal (healthy weight)';
            } 
            elseif($result >25 && $result <=30)
            {
                $category='overweight';
            } 
            elseif($result > 30 && $result <=35)
            {
                $category='moderately obese';
            } 
            elseif($result >35 && $result <=40)
            {
                $category='severely obese';
            } 
            elseif($result > 40)
            {
                $category='very severely obese';
            } 

            $item=array(
                'pathway' => $params['pathway'],
                'step'      => $step['number'],
                'user_id'   => $params['user_id'],
                'value'     => $result,
                'result_caption'=> $category
            );
            // echo 'result = '.$result; print_r($item);exit;
            $st=$this->db->select('*')
                        ->from('step_answers')
                        ->where('step',$step['number'])
                        ->where('pathway',$data['pathway'])
                        ->where('user_id',$params['user_id'])
                        ->get()
                        ->result_array();
            // print_r($this->db->last_query());exit;
            if(count($st)>0)
            {
                $this->db->where('step',$item['step'])->update('step_answers',$item);
            }
            else
            {
                $this->db->insert('step_answers',$item);
            }
            // calculate percent
            $d=count($this->db->select('*')->from('steps')->where('pathway',$params['pathway'])
                        ->get()->result_array());
            $percent=round(($step['number']/$d)*100);
            // Save Current Step and save percent in pathway status
            $item=array(
                    'user_id'   =>  $params['user_id'],
                    'pathway'   =>  $params['pathway'],
                    'current_step'  =>  $step['number'],
                    'percent'   =>  $percent
            );
            $st=$this->db->select('*')->from('user_pathway_status')
                        ->where('user_id', $params['user_id'])
                        ->where('pathway', $params['pathway'])
                        ->get()->result_array();
            if(count($st)>0)
            {
                $this->db->where('user_id', $params['user_id'])
                            ->where('pathway', $params['pathway'])
                            ->update('user_pathway_status', $item);
            }
            else
            {
                $this->db->insert('user_pathway_status', $item);
            }

            $step=$this->getNextStep($step,$params);
            // print_r($step);exit;
            $next=$this->db->query('select * from pathflow where step='.$step['number'].' and pathway='.$params['pathway'])->result_array();
            $data['step']=$step['number'];
            $data['back']=$next[0]['back'];
            $data['next']=$next[0]['next'];
            if($step['type']=='question' || $step['type']=='info')
            {
                // echo "<script>console.log('1643 next step is question')</script>";
                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
                // echo $this->db->last_query();
                $data['question']=$st[0];
                $data['question']['statement']= 'Your BMI is '.number_format($result,2).'. Your result suggests you are '.$category;
                return $data;
            }
        }
        
        
    }
    public function getStepNumber($id)
    {
        $st=$this->db->query('select * from steps where id='.$id)->result_array();
        return $st[0]['number'];
    }
    
    public function getBackPathwayQuestion($params)
    {
        $step=$this->getStepByNumber($params['step'], $params['pathway']);
        // echo '<pre>';print_r($step);exit;
        
        $st=$this->db->select('*')->from('pathflow')
                ->where('pathway',$params['pathway'])
                ->where('step',$params['step'])
                ->where('next',$params['next'])
                ->get()->result_array();
        //echo $this->db->last_query();exit;
        $data=$st[0];
        $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$step['id'])->result_array();
        $data['question']=$st[0];
        $steps=count($this->db->select('*')->from('steps')
                    ->where('pathway',$params['pathway'])
                    ->get()->result_array());
        $data['percent']=($params['step']/$steps)*100;

        return $data;
    }    
    public function checkUser($data)
    {
        $st=$this->db->select('*')->from('users')
            ->WHERE('email',$data['email'])
            ->WHERE('password',md5(sha1($data['password'])))
            ->get()->result_array();
        if(count($st)>0)
        {
            return $st[0];
        }
        else
        {
            return false;
        }
    }
    ///////////////////////////////////////
    ///                                 ///
    ///     Admin Menu Section Starts   ///
    ///                                 ///
    ///////////////////////////////////////
    public function getMenuParents()
    {
        return $this->db->select('*')->from('admin_menu')->where('parent',0)->get()->result_array();
    }
    public function addMenuItem($data)
    {
        $item=array(
            'parent'=>$data['parent'],
            'name'=>$data['name'],
            'class'=>$data['class'],
            'url'=>$data['url']
        );

        $this->db->insert('admin_menu',$item);
    }
    public function updateMenuItem($data,$menuId)
    {
        $item=array(
            'parent'=>$data['parent'],
            'name'=>$data['name'],
            'class'=>$data['class'],
            'url'=>$data['url']
        );

        $this->db->WHERE('id',$menuId)->update('admin_menu',$item);
    }
    public function getMenuItems()
    {
        $st=$this->db->select('*')->from('admin_menu')->where('parent',0)->get()->result_array();
        if(count($st)>0)
        {
            for($i=0;$i<count($st);$i++)
            {
                $st[$i]['child']=$this->db->select('*')->from('admin_menu')->where('parent',$st[$i]['id'])->get()->result_array();
            }
            return $st;
        }
        else
        {
            return false;
        }

    }
    public function getAllMenuItems()
    {
        return $this->db->query('SELECT admin_menu.*, a.name as parent from admin_menu left join admin_menu a on a.id=admin_menu.parent')->result_array();
    }
    public function getMenuItemDetail($menuId)
    {
        $st=$this->db->select('*')->from('admin_menu')->WHERE('id',$menuId)->get()->result_array();
        return $st[0];
    }
    public function delAdminMenu($id)
    {
        $this->db->query('DELETE from admin_menu WHERE id='.$id);
    }
    ///////////////////////////////////////
    ///                                 ///
    ///     Admin Menu Section Ends     ///
    ///                                 ///
    ///////////////////////////////////////
    public function getAll($table)
    {
        return $this->db->select('*')->from($table)->get()->result_array();
    }
    public function getAllById($table,$id)
    {
        $st= $this->db->select('*')->from($table)->WHERE('id',$id)->get()->result_array();
        return $st[0];
    }

    // Pathway Starts

    public function addPathway($data)
    {
        $item=array(
            'name'=>$data['name']
        );

        $this->db->insert('pathways',$item);
        return true;
    }
    public function updatePathway($data,$menuId)
    {
        $item=array(
            'name'=>$data['name']
        );

        $this->db->WHERE('id',$menuId)->update('pathways',$item);
    }

    public function getPublishedPathways()
    {
        return $this->db->select('*')
                        ->from('pathways')
                        ->where('publish','yes')
                        ->get()
                        ->result_array();
    }
    public function getUserPublishedPathways($user_id)
    {
        $pathways=$this->db->select('*')
                        ->from('pathways')
                        ->where('publish','yes')
                        ->order_by('orders', 'asc')
                        ->get()
                        ->result_array();
        for($i=0;$i<count($pathways);$i++)
        {
            $st=$this->db->select('*')->from('user_pathway_status')->where('user_id',$user_id)
                        ->where('pathway', $pathways[$i]['id'])
                        ->get()->result_array();
            if(count($st)>0)
            {
                $pathways[$i]['percent']=$st[0]['percent'];
                $pathways[$i]['attempt']=date('d-m-Y h:i:s', strtotime($st[0]['created_at']));
            }
            else
            {
                $pathways[$i]['percent']='0';
            }
        }
        return $pathways;
    }


    public function addQuestion($data)
    {
        $item=array(
            'statement' =>  $data['statement'],
            'pathway'   =>  $data['pathway'],
            'type'      =>  $data['type']
        );

        $this->db->insert('questions',$item);
        return true;
    }

    public function getQuestions()
    {
        $st=$this->db->query('SELECT questions.*, pathways.name as pathwayName from questions inner join pathways
            on 
            pathways.id=questions.pathway')->result_array();
        return $st;
    }

    public function getQuestionByStep($id)
    {
        $st=$this->db->query('SELECT questions.* from questions inner join step_questions sq
                        on sq.question=questions.id where sq.step='.$id)->result_array();
        if(count($st)>0)
        {
            return $st[0];
        }
        else
        {
            return array();
        }
        
    }
    public function addAnsModel($data)
    {
        $item=array(
            'label' =>  $data['label'],
            'text' =>  $data['textboxes'],
            'radio' =>  $data['radioboxes'],
            'checkbox' =>  $data['checkboxes'],
            'textarea' =>  $data['textarea'],
            'selectbox'    => $data['dropdown']
        );

        $this->db->insert('answer_models',$item);
        return true;
    }

    public function getAnsForm($qId, $params)
    {
        
        if($params['pathway']==4)
        {
            $data=array();
            $data[0]=$this->db->select('*')->from('ans_form')->where('question',$qId)->get()->result_array();
            // print_r($data[0]);
            if(count($data[0])>0)
            {
                if(strtolower($params['gender'])=='male')
                {
                    $d['form']=array();
                    for($i=0;$i<count($data[0]);$i++)
                    {
                        if(strtolower($data[0][$i]['caption'])==strtolower('pregnancy'))
                        {
                            unset($data[0][$i]);
                        }
                        // else
                        // {
                        //     $d['form'][$i]=$data[0][$i];                            
                        // }
                    }
                    // $data[0]=$d['form'];
                    return array_values($data[0]); 
                }
                else
                {
                    return $data[0];
                }
                // 
                

                //$key = array_search('green', $array);
                //\unset($array[1]);
            }
            else
            {
                return array();
            }
                          
        }
        else
        {
            return $this->db->select('*')->from('ans_form')->where('question',$qId)->get()->result_array();
        }
        
    }

    public function assign_ans_model($data, $id)
    {
        $item=array(
            'ans_model' =>  $data['ans_model']
        );

        $this->db->where('id',$id)->update('questions',$item);
        return true;
    }

    public function addStep($data)
    {
        $item=array(
            'number' =>  $data['number'],
            'pathway'   =>  $data['pathway'],
            'type'   =>  $data['type']
        );

        $this->db->insert('steps',$item);
        return true;
    }

    public function addStepQuestion($data)
    {
        $item=array(
            'step'      =>  $data['step'],
            'question'  =>  $data['question']
        );
        $this->db->insert('step_questions',$item);
    }
    public function addStepCalculation($data)
    {
        $item=array(
            'step'      =>  $data['step'],
            'from_step'  =>  $data['from_step'],
            'to_step'  =>  $data['to_step']
        );
        $this->db->insert('step_calculation',$item);
    }

    public function addStepCondition($data)
    {
        $item=array(
            'step'      =>  $data['step'],
            'step_result'  =>  $data['step_result'],
            'operator'  =>  $data['operator'],
            'value'  =>  $data['value'],
            'if_next_step'  =>  $data['if_next_step'],
            'else_next_step'  =>  $data['else_next_step']
        );
        $this->db->insert('step_condition',$item);
    }

    public function addStepAge($data)
    {
        $item=array(
            'step'      =>  $data['step'],
            'operator'  =>  $data['operator'],
            'value'  =>  $data['value'],
            'if_next_step'  =>  $data['if_next_step'],
            'else_next_step'  =>  $data['else_next_step']
        );
        $this->db->insert('step_age',$item);
    }

    public function addStepFlag($data)
    {
        $item=array(
            'step'      =>  $data['step'],
            'if_next_step'  =>  $data['if_next_step'],
            'else_next_step'  =>  $data['else_next_step']
        );
        $this->db->insert('step_flag',$item);
    }

    public function addStepFormula($data)
    {
        $item=array(
            'step'      =>  $data['step'],
            'if_next_step'  =>  $data['if_next_step'],
            'else_next_step'  =>  $data['else_next_step']
        );
        $this->db->insert('step_formula',$item);
    }

    public function addStepGender($data)
    {
        $item=array(
            'step'      =>  $data['step'],
            'operator'  =>  $data['operator'],
            'value'  =>  $data['value'],
            'if_next_step'  =>  $data['if_next_step'],
            'else_next_step'  =>  $data['else_next_step']
        );
        $this->db->insert('step_gender',$item);
    }

    public function addNextPFinDb($data)
    {
        $st=$this->db->query('select * from pathflow where step='.$data['step'])->result_array();
        if(count($st)>0)
        {
            $item=array(
            'pathway'   =>      $data['pathway'],
            'step'      =>      $data['step'],
            'next'      =>      $data['next'],
            'back'      =>      $data['back']
            );
            $this->db->where('step',$item['step'])->update('pathflow',$item);
        }
        else
        {
            $item=array(
            'pathway'   =>      $data['pathway'],
            'step'      =>      $data['step'],
            'next'      =>      $data['next'],
            'back'      =>      $data['back']
            );
            $this->db->insert('pathflow',$item);
        }
        
    }

    public function getAllSteps()
    {
        $steps=$this->db->query('SELECT steps.*, pathways.name as pathway from steps inner join pathways on pathways.id=steps.pathway ')->result_array();
        for($i=0;$i<count($steps);$i++)
        {
            if($steps[$i]['type']=='question' || $steps[$i]['type']=='info')
            {
                $steps[$i]['content']=$steps[$i]['type'];
            }
            else
            {
                $steps[$i]['content']='';
            }
        }
    }

    public function getPathFlowSteps($id)
    {
        return $this->db->query('SELECT steps.*, pathways.name as pathway from steps inner join pathways on pathways.id=steps.pathway  where steps.pathway='.$id)->result_array();
    }

    public function getPathFlowByStep($id, $pathway)
    {
        $st=$this->db->select('*')->from('pathflow')->where('step',$id)->where('pathway',$pathway)->get()
        ->result_array();
        return $st[0];
    }

    public function getStepByNumber($id,$pathway)
    {
        $st=$this->db->select('*')->from('steps')->where('number',$id)->where('pathway',$pathway)->get()->result_array();
        return $st[0];
    }

    public function getQByPathway($id)
    {
        return $this->db->select('*')->from('questions')->where('pathway',$id)->get()->result_array();
    }

    public function getAMByQ($id)
    {
        $st= $this->db->query('SELECT * from answer_models inner join questions on questions.ans_model=answer_models.id where questions.id='.$id)->result_array();
        return $st[0];
    }

    public function saveAns_form($q,$am,$data)
    {
        if($am['text']>0){
            $type='text';
        }
        if($am['radio']>0){
            $type='radio';
            for($i=0;$i<$am['radio'];$i++)
            {
                $item=array(
                    'question'  => $q,
                    'name' => $data['name'],
                    'type'  => $type,
                    'value' =>$data['radio'.($i+1)],
                    'caption'=>$data['radioTxt'.($i+1)] 
                );
                $this->db->insert('ans_form',$item);
            }
        }
        if($am['checkbox']>0){
            $type='checkbox';
        }
        if($am['selectbox']>0){
            $type='select';
        }
        if($_POST['flag'])
        {
            $this->session->set_userdata('flag',$_POST['flag']);
        }

    }


    public function saveResult($data)
    {
        
        $step=$this->getStepByNumber($data['step'], $data['pathway']);
        // print_r($step);
        if($step['type']=='question' || $step['type']=='info')
        {
            $st=$this->db->query('Select questions.* from questions inner join step_questions on step_questions.question=questions.id where step_questions.step='.$step['id'])->result_array();
            
            if($st[0]['ans_model'])
            {
               $am=$this->getAllById('answer_models',$st[0]['ans_model']);
               // print_r($am);exit;
                if($am['text']>0)
                {
                    // echo 'it works';
                    //echo $am['text'].' textboxes <br>';
                    $ans_form=$this->getAnsForm($st[0]['id'], $data);
                    // echo '<pre>';print_r($ans_form);print_r($data);exit;
                    for($i=0;$i<count($ans_form);$i++)
                    {
                        $item=array(
                            'pathway'   => $data['pathway'],
                            'step'      => $data['step'],
                            'value'     => $data[$ans_form[$i]['name']],
                            'field_name'=>$ans_form[$i]['name'],
                            'user_id'   =>$data['user_id']
                        );
                        
                        // echo '1050 <pre>';print_r($item);
                        $st=$this->db->select('*')
                                    ->from('step_answers')
                                    ->where('step',$data['step'])
                                    ->where('user_id',$data['user_id'])
                                    ->where('pathway', $data['pathway'])
                                    ->where('field_name',$ans_form[$i]['name'])
                                    ->get()
                                    ->result_array();
                        // echo $this->db->last_query();
                        // print_r($st);exit;
                        if(count($st)>0)
                        {
                            $this->db->where('step',$data['step'])
                                    ->where('user_id',$data['user_id'])
                                    ->where('pathway', $data['pathway'])
                                    ->where('field_name',$ans_form[$i]['name'])
                                    ->update('step_answers',$item);
                        }
                        else
                        {
                            
                            $this->db->insert('step_answers',$item);
                            // echo $this->db->last_query();
                        }
                        
                    }
                    //echo 'answer inserted';exit;
                    
                }
                if($am['datepicker']>0)
                {
                    //echo $am['text'].' textboxes <br>';
                    $ans_form=$this->getAnsForm($st[0]['id'], $data);
                    //echo '<pre>';print_r($ans_form);exit;
                    
                    $item=array(
                        'pathway'   => $data['pathway'],
                        'step'      => $data['step'],
                        'value'     => $data[$ans_form[0]['name']],
                        'field_name'=>$ans_form[0]['name'],
                        'user_id'   =>$data['user_id']
                    );
                    
                    
                    $st=$this->db->select('*')
                                ->from('step_answers')
                                ->where('step',$data['step'])
                                ->where('user_id',$data['user_id'])
                                ->where('field_name',$ans_form[0]['name'])
                                ->where('pathway', $data['pathway'])
                                ->get()
                                ->result_array();
                    //echo '1050 <pre>';print_r($st);exit;
                    if(count($st)>0)
                    {
                        $this->db->where('step',$data['step'])
                                ->where('user_id',$data['user_id'])
                                ->where('field_name',$ans_form[0]['name'])
                                ->where('pathway', $data['pathway'])
                                ->update('step_answers',$item);
                    }
                    else
                    {
                        
                        $this->db->insert('step_answers',$item);
                    }
                    
                    
                    //echo 'answer inserted';exit;
                    
                }
                if($am['radio']>0)
                {
                    // echo "<script>console.log('2196 saving radio data for step ".$data['step']." and value is ".$data['score']."')</script>";
                    $item=array(
                        'pathway'   => $data['pathway'],
                        'step'      => $data['step'],
                        'value'     => $data['score'],
                        'user_id'   => $data['user_id']
                    );
                    
                    // echo '<pre> path';print_r($pth);exit;
                    $st=$this->db->select('*')
                                ->from('step_answers')                                
                                ->where('user_id',$data['user_id'])
                                ->where('pathway', $data['pathway'])
                                ->where('step',$data['step'])
                                ->get()
                                ->result_array();

                    // echo '<pre>';print_r($st);
                    // $st=$this->db->query('select * from step_answers where step='.$data['step'])->result_array();
                    if(count($st)>0)
                    {
                        
                        $this->db->where('step',$data['step'])
                                ->where('user_id',$data['user_id'])
                                ->where('pathway', $data['pathway'])
                                ->update('step_answers',$item);
                        // echo $this->db->last_query();
                    }
                    else
                    {                        
                        $this->db->insert('step_answers',$item);
                        // echo $this->db->last_query();
                    }
                }
                if($am['checkbox']>0)
                {
                    // echo "<script>console.log('2205 saving checkbox data for step ".$data['step']."')</script>";
                    // echo '2129<pre>';print_r($data);exit;
                    $item=array(
                        'pathway'   => $data['pathway'],
                        'step'      => $data['step'],
                        'user_id'   =>$data['user_id'],
                        'value'     => implode(',', $data['score'])
                    );
                    // print_r($item);exit;
                    $st=$this->db->select('*')
                                ->from('step_answers')
                                ->where('step',$data['step'])
                                ->where('user_id',$data['user_id'])
                                ->where('pathway', $data['pathway'])
                                ->get()
                                ->result_array();
                    if(count($st)>0)
                    {
                        
                        $this->db->where('step',$data['step'])
                                ->where('user_id',$data['user_id'])
                                ->where('pathway', $data['pathway'])
                                ->update('step_answers',$item);
                    }
                    else
                    {
                        
                        $this->db->insert('step_answers',$item);
                    }
                } 
            }
            $d=count($this->db->select('*')->from('steps')->where('pathway',$data['pathway'])
                        ->get()->result_array());
            $percent=round(($data['step']/$d)*100);
            $item=array(
                    'user_id'       =>  $data['user_id'],
                    'practice_id'   =>  $data['practice_id'],
                    'pathway'       =>  $data['pathway'],
                    'current_step'  =>  $data['step'],
                    'percent'       =>  $percent
            );
            $st=$this->db->select('*')->from('user_pathway_status')
                        ->where('user_id', $data['user_id'])
                        ->where('pathway', $data['pathway'])
                        ->get()->result_array();
            if(count($st)>0)
            {
                $this->db->where('user_id', $data['user_id'])
                            ->where('pathway', $data['pathway'])
                            ->update('user_pathway_status', $item);
            }
            else
            {
                $this->db->insert('user_pathway_status', $item);
            }
        }
        $this->changeIsSubmittedStatus($data, 'no');
        return true;

    }

    public function changeIsSubmittedStatus($data, $status)
    {
        // print_r($data);exit;
        $this->db->query('update user_pathway_status set is_submitted='.$status.' where pathway='.$data['pathway'].' and user_id='.$data['user_id']);
        return true;
    }
    public function getStepByNumberPathway($step, $pathway)
    {
        $st=$this->db->select('*')->from('steps')->where('number',$step)->where('pathway', $pathway)->get()->result_array();
        return $st[0];
    }
    // Modified
    public function getStepAnswer($data)
    {
        if($data['pathway']==3)
        {
            $ans=$this->db->query('SELECT * FROM step_answers WHERE pathway=3 and step=1 and user_id='.$data['user_id'].' ORDER BY created_at DESC LIMIT 0,2')->result_array();
            $data=$ans;
            return array_reverse($data);
        }
        $st=$this->db->select('*')
                ->from('step_answers')
                ->where('step',$data['step'])
                ->where('user_id',$data['user_id'])
                ->where('pathway', $data['pathway'])
                ->get()
                ->result_array();
                // print_r($st);
        // echo $this->db->last_query();
        if(count($st)>0)
        {
            // return $st;
            if(count($st)>1)
            {
                return $st;
            }
            else
            {
                return $st[0];
            }              
        }
        else
        {
            return array();
        }
            
        
        
    }

    public function getStepAnswerforBMI($data)
    {        
        $st=$this->db->select('*')
                ->from('step_answers')
                ->where('step',$data['step'])
                ->where('user_id',$data['user_id'])
                ->where('pathway', $data['pathway'])
                ->get()
                ->result_array();
                // print_r($st);
        // echo $this->db->last_query();
        if(count($st)>0)
        {
            // return $st;
            if(count($st)>1)
            {
                return $st;
            }
            else
            {
                return $st[0];
            }              
        }
        else
        {
            return array();
        }
            
        
        
    }

    public function pathway_review($params)
    {
        
        $st=$this->db->select('*')
                        ->from('step_answers')
                        ->where('user_id',$params['user_id'])
                        ->where('pathway', $params['pathway'])
                        ->get()
                        ->result_array();
        
        $data=array();
        // print_r($st);
        $count=count($st);
        $i=0;
        
        if($params['pathway']!=3)
        {
            foreach($st as $row)
            {
                $step=$this->getStepByNumber($row['step'], $params['pathway']);

                if($step['type']=='question' || $step['type']=='info' || $step['type']=='datepicker'){
                    $d=$this->db->query('select * from steps where pathway='.$params['pathway'].' and number='.$row['step'])->result_array();

                    $q=$this->getQuestionByStep($d[0]['id']);
                    $path=$this->getPathFlowByStep($row['step'], $params['pathway']);
                    if($q['ans_model']==16)
                    {
                        $st=$this->db->select('*')
                                ->from('step_answers')
                                ->where('pathway',$params['pathway'])
                                ->where('step',$step['number'])
                                ->where('user_id',$params['user_id'])
                                ->get()
                                ->result_array();
                        $d=array();
                        $d[0]['value']=$st[0]['value'];
                        $dr=array(
                                'type'      => $step['type'],
                                'question'  => $q['statement'],
                                'answer'    => $d,
                                'step'      => $path['step'],
                                'back'      => $path['back'],
                                'next'      => $path['next'],
                                'can_submit'    =>  $this->getCanSubmit($params),
                                'is_submitted'  =>  $this->getIsSubmitted($params)
                            );
                    }
                    else
                    {
                        if($q)
                        {
                            $dr=array(
                                'type'      => $step['type'],
                                'question'  => $q['statement'],
                                'answer'    => ($this->getAnswerResult($q['id'],$row['value'])),
                                'step'      => $path['step'],
                                'back'      => $path['back'],
                                'next'      => $path['next'],
                                'can_submit'    =>  $this->getCanSubmit($params),
                                'is_submitted'  =>  $this->getIsSubmitted($params)
                            );
                        }
                        else
                        {
                            $dr=array(
                                'type'      => $step['type'],
                                'question'  => array(),
                                'answer'    => array(),
                                'step'      => $path['step'],
                                'back'      => $path['back'],
                                'next'      => $path['next'],
                                'can_submit'    =>  $this->getCanSubmit($params),
                                'is_submitted'  =>  $this->getIsSubmitted($params)
                            );
                        }                    
                        
                    }
                    array_push($data, $dr);
                        
                }
            }
        }
        else
        {
            $data=array();
            $d=$this->db->query('select * from steps where pathway=3 and number=1')->result_array();
            $q=$this->getQuestionByStep($d[0]['id']);
            $path=$this->getPathFlowByStep(1, 3);
            $ans=$this->db->query('SELECT * FROM step_answers WHERE pathway=3 and step=1 and user_id='.$params['user_id'].' ORDER BY created_at DESC LIMIT 0,2')->result_array();
            // print_r(array_reverse($ans));exit;
            $data[0]['type']='question';
            $data[0]['question']=$q['statement'];
            $data[0]['answer']=array_reverse($ans);  
            $data[0]['step']=$path['step'];
            $data[0]['back']=$path['back'];
            $data[0]['next']=$path['next'];
        }

        return $data;;
    }
    public function getCanSubmit($params)
    {
        $st=$this->db->select('can_submit')->from('user_pathway_status')
                        ->where('user_id', $params['user_id'])
                        ->where('pathway', $params['pathway'])
                        ->get()
                        ->result_array();
        return $st[0]['can_submit'];
    }

    public function getIsSubmitted($params)
    {
        $st=$this->db->select('is_submitted')->from('user_pathway_status')
                        ->where('user_id', $params['user_id'])
                        ->where('pathway', $params['pathway'])
                        ->get()
                        ->result_array();
        return $st[0]['is_submitted'];
    }
    public function pathway_review_for_BS($params)
    {
        
        $st=$this->db->select('*')
                        ->from('step_answers')
                        ->where('user_id',$params['user_id'])
                        ->where('pathway', $params['pathway'])
                        ->get()
                        ->result_array();
        
        $data=array();
        // print_r($st);
        $count=count($st);
        $i=0;
        
        if($params['pathway']!=3)
        {
            foreach($st as $row)
            {
                $step=$this->getStepByNumber($row['step'], $params['pathway']);

                if($step['type']=='question' || $step['type']=='info' || $step['type']=='datepicker')
                {
                    $d=$this->db->query('select * from steps where pathway='.$params['pathway'].' and number='.$row['step'])->result_array();

                    $q=$this->getQuestionByStep($d[0]['id']);
                    $path=$this->getPathFlowByStep($row['step'], $params['pathway']);
                    if($q['ans_model']==16)
                    {
                        $st=$this->db->select('*')
                                ->from('step_answers')
                                ->where('pathway',$params['pathway'])
                                ->where('step',$step['number'])
                                ->where('user_id',$params['user_id'])
                                ->get()
                                ->result_array();
                        $d=array();
                        $d[0]['value']=$st[0]['value'];
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
                                'selected_choice'    => ($this->getAnswerResult_for_BS($q['id'],$row['value']))
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
                    array_push($data, $dr);
                        
                }
            }
        }
        else
        {
            $data=array();
            $d=$this->db->query('select * from steps where pathway=3 and number=1')->result_array();
            $q=$this->getQuestionByStep($d[0]['id']);
            $path=$this->getPathFlowByStep(1, 3);
            $ans=$this->db->query('SELECT * FROM step_answers WHERE pathway=3 and step=1 and user_id='.$params['user_id'].' ORDER BY created_at DESC LIMIT 0,2')->result_array();
            // print_r(array_reverse($ans));exit;
            $data[0]['type']='question';
            $data[0]['question']=$q['statement'];
            $data[0]['answer']=array_reverse($ans);  
            $data[0]['step']=$path['step'];
            $data[0]['back']=$path['back'];
            $data[0]['next']=$path['next'];
        }

        return $data;;
    }

    public function getEditedQuestion($params)
    {
        $st=$this->db->query("SELECT * from steps where number=".$params['step']." and pathway=".$params['pathway'])->result_array();
        $step=$st[0];
        $q=$this->getQuestionByStep($step['id']);
        $data['question']=$q;
        return $data;
    }

    public function getAnswerResult($q, $v)
    {
        $d=$this->db->query('select * from answer_models inner join questions on questions.ans_model=answer_models.id 
            where questions.id='.$q.'
            ')->result_array();
        // echo 'Q=:';print_r($d[0]);echo'<br>';
        $arr=array();
        if(strpos($v, ','))
        {
            $arr=explode(',', $v);            
        }
        if(count($arr)>0)
        {
            $caption=array();
            $caption[0]['value']='';
            for($i=0;$i<count($arr);$i++)
            {
                $st=$this->db->select('caption')
                    ->from('ans_form')
                    ->where('question', $q)
                    ->where('value',$arr[$i])
                    ->get()
                    ->result_array();
                // print_r($st[0]);
                if(count($st)>0)
                {
                    $caption[0]['value'].=($i+1).': '.$st[0]['caption'].' ';
                }
                
            }
            // print_r($caption);
            return $caption;
        }
        else
        {
            $caption=array();
            $st=$this->db->select('caption')
                    ->from('ans_form')
                    ->where('question', $q)
                    ->where('value',$v)
                    ->get()
                    ->result_array();
            // echo $this->db->last_query();
            if(count($st)>0)
            {
                $caption[0]['value']=$st[0]['caption'];
                return $caption;
            }
            else
            {
                return array();
            }
        }



    }

    public function getAnswerResult_for_BS($q, $v)
    {
        $d=$this->db->query('select * from answer_models inner join questions on questions.ans_model=answer_models.id 
            where questions.id='.$q.'
            ')->result_array();
        // echo 'Q=:';print_r($d[0]);echo'<br>';
        $arr=array();
        if(strpos($v, ','))
        {
            $arr=explode(',', $v);            
        }
        if(count($arr)>0)
        {
            $caption=array();
            $caption[0]['value']='';
            for($i=0;$i<count($arr);$i++)
            {
                $st=$this->db->select('caption')
                    ->from('ans_form')
                    ->where('question', $q)
                    ->where('value',$arr[$i])
                    ->get()
                    ->result_array();
                // print_r($st[0]);
                if(count($st)>0)
                {
                    $caption[0]['value'].=($i+1).': '.$st[0]['caption'].' ';
                }
                
            }
            // print_r($caption);
            return $caption;
        }
        else
        {
            $caption=array();
            $st=$this->db->select('caption')
                    ->from('ans_form')
                    ->where('question', $q)
                    ->where('value',$v)
                    ->get()
                    ->result_array();
            // echo $this->db->last_query();
            if(count($st)>0)
            {
                $caption[0]['value']=$st[0]['caption'];
                return $caption[0]['value'];
            }
            else
            {
                return array();
            }
        }



    }

    public function flush_pw_results($user_id, $pathway)
    {
        $this->db->query('delete from step_answers where user_id='.$user_id.' 
                            and pathway='.$pathway);
    }

    public function savePercent($data)
    {
        $item=array(
            'percent'   => 100
        );

        $this->db->where('pathway', $data['pathway'])->where('user_id', $data['user_id'])
                    ->update('user_pathway_status',$item);
        // echo $this->db->last_query();
    }

    public function countPathwaySteps($pathway)
    {
        $count=count($this->db->select("*")->from('steps')->where('pathway', $pathway)->get()->result_array());
        return $count;
    }

    public function updateStats($params)
    {
        $item=array(
                'user_id'   =>  $params['user_id'],
                'pathway'   =>  $params['pathway'],
                'current_step'  =>  $params['step'],
                'percent'   =>  $params['percent']
        );
        $st=$this->db->select('*')->from('user_pathway_status')
                    ->where('user_id', $params['user_id'])
                    ->where('pathway', $params['pathway'])
                    ->get()->result_array();
        if(count($st)>0)
        {
            $this->db->where('user_id', $params['user_id'])
                        ->where('pathway', $params['pathway'])
                        ->update('user_pathway_status', $item);
        }
        else
        {
            $this->db->insert('user_pathway_status', $item);
        }
    }

    public function getStats($params)
    {
        $st=$this->db->select('*')->from('user_pathway_status')
                    ->where('user_id', $params['user_id'])
                    ->where('pathway', $params['pathway'])
                    ->get()->result_array();
        return $st[0];
    }

    public function removeAnswers($params)
    {
        $st=$this->db->select('*')
                ->from('step_answers')
                ->where('user_id',$params['user_id'])
                ->where('pathway', $params['pathway'])
                ->where('step >', $params['step'])
                ->get()
                ->result_array();
        if(count($st)>0)
        {
            $this->db->query('Delete from step_answers where user_id='.$params['user_id'].'
             and pathway='.$params['pathway'].' and step > '.$params['step']);
        }
        
    }

    public function getPathwayName($id)
    {
        $st=$this->db->select('name')->from('pathways')->where('id', $id)->get()->result_array();
        return $st[0]['name'];
    }

}
