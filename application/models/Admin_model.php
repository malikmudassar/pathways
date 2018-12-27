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
    public function getFirstPathwayQuestion($id)
    {
        $st=$this->db->select('*')->from('pathflow')->where('pathway',$id)->where('back',0)->get()->result_array();
        //echo '<pre>';print_r($this->db->last_query());exit;
        if(!count($st)>0)
        {
            return false;
        }
        $data=$st[0];
        $step=$this->getStepByNumberPathway($data['step'],$data['pathway']);
        $data['step']=$step['id'];
        if(empty($data['step']))
        {
            return false;
        }
        //echo '<pre>';print_r($step);exit;
        $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$data['step'].' and pathway='.$data['pathway'])->result_array();
        //echo '<pre>';print_r($this->db->last_query());exit;
        $data['question']=$st[0];
        $data['percent']=0;
        return $data;
    }

    public function getNextPathwayQuestion($params)
    {        
        //echo '<pre>1';print_r($params); exit;
        $st=$this->db->select('*')->from('pathflow')
                ->where('pathway',$params['pathway'])
                ->where('step',$this->getStepNumber($params['next']))
                ->get()->result_array();
        $data=$st[0];
        $step=$this->getStepByNumber($data['step']);
        
        $result=0;
        
        if($step['type']=='question')
        {
            echo "<script>console.log('44. next step is question')</script>";
            $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.($params['next']).' and pathway='.$params['pathway'])->result_array();
            $data['question']=$st[0];
            //echo '<pre>';print_r($this->db->last_query()); exit;
            $pth=$this->db->select('*')
                    ->from('user_pathway_status')
                    ->where('user_id',$params['user_id'])
                    ->where('pathway',$data['pathway'])
                    ->where('status','pending')
                    ->get()
                    ->result_array();
                    //echo $this->db->last_query();exit;
            if($data['next']==0)
            {   echo "<script>console.log('62. data[next] is 0')</script>";               
                if(count($pth)>0)
                {
                    $item=array(
                        'user_id'   =>  $params['user_id'],
                        'pathway'   =>  $data['pathway'],
                        'current_step'=>$data['step'],
                        'finished_at' =>date('Y-m-d h:i:s'),
                        'status'    =>  'finished',
                        'percent'   =>  100
                    );
                    $this->db->where('id',$pth[0]['id'])->update('user_pathway_status',$item);
                }
                $data['percent']=100;
            }
            else
            {
                echo "<script>console.log('77. data[next] is not 0')</script>";
                if(count($pth)>0)
                {
                    echo "<script>console.log('80. user_pathway_status found')</script>";
                    $steps=count($this->db->select('*')->from('steps')
                        ->where('pathway',$data['pathway'])
                        ->get()->result_array());
                    $item=array(
                        'user_id'   =>  $params['user_id'],
                        'pathway'   =>  $data['pathway'],
                        'current_step'=>$data['step'],
                        'status'    =>  'pending',
                        'percent'   =>  ($data['step']/$steps)*100
                    );
                    $this->db->where('id',$pth[0]['id'])->update('user_pathway_status',$item);
                }
                else
                {
                    echo "<script>console.log('95 user_pathway_status not found')</script>";
                }
            }
            //echo '<pre>';print_r($item);exit;
            $data['percent']=$item['percent'];
            return $data;
        }
        else
        {
            echo "<script>console.log('98. next step ".$step['id']." is not question')</script>";
            $data=$this->checkNextStep($step,$params);
            $steps=count($this->db->select('*')->from('steps')
                        ->where('pathway',$data['pathway'])
                        ->get()->result_array());
            $data['percent']=($data['step']/$steps)*100;
            return $data;
        }
        
        
    }

    public function getNextStep($step, $params){

        $next=$this->db->query('select * from pathflow where step='.$step['id'].' and pathway='.$params['pathway'])->result_array();

        //echo '<pre>';print_r($next);exit;
        return $this->getStepByNumber($next[0]['next']);
    }
    public function checkNextStep($step,$params)
    {
        //echo '<pre>';print_r($step);exit;
        
        $data=$params;
        $result=0;
        if($step['type']=='calculation')
        {
            //echo 'In calculation';exit;
            echo "<script>console.log('76 Step ".$step['id']." is calculation')</script>";
            $st=$this->db->query('select * from step_calculation where step='.$step['id'])->result_array();
            $stepCalcData=$st[0];
            // Get list of answers from answers table against the range above
            $pth=$this->db->select('*')
                        ->from('user_pathway_status')
                        ->where('user_id',$data['user_id'])
                        ->where('pathway',$data['pathway'])
                        ->where('status','pending')
                        ->get()
                        ->result_array();
            $st=$this->db->select('*')
                        ->from('step_answers')
                        ->where('step BETWEEN '.$stepCalcData['from_step'].' and '.$stepCalcData['to_step'].'')
                        ->where('user_id',$params['user_id'])
                        ->where('created_at >',$pth[0]['started_at'])
                        ->get()
                        ->result_array();
            //echo '<pre>';print_r($st);exit;
            // $st=$this->db->query('select * from step_answers where step BETWEEN '.$stepCalcData['from_step'].' and '.$stepCalcData['to_step'].'')->result_array();
            
            if(count($st)>0)
            {
                for($i=0;$i<count($st);$i++)
                {
                    $result+=$st[$i]['value'];
                }
            }
            echo "<script>console.log('89 saving result ".$result." for step ".$step['id']."')</script>";
            $item=array(
                'pathway' => $params['pathway'],
                'step'      => $step['id'],
                'user_id'   => $params['user_id'],
                'value'     => $result
            );
            $st=$this->db->select('*')
                        ->from('step_answers')
                        ->where('step',$step['id'])
                        ->where('user_id',$params['user_id'])
                        ->where('created_at >',$pth[0]['started_at'])
                        ->get()
                        ->result_array();
            //echo '<pre>';print_r($st);exit;
            // $st=$this->db->query('select * from step_answers where step='.$data['step'])->result_array();
            if(count($st)>0)
            {
                
                $this->db->where('step',$item['step'])->update('step_answers',$item);
            }
            else
            {
                
                $this->db->insert('step_answers',$item);
            }
            // $this->saveResult(array(
            //     'pathway' => $params['pathway'],
            //     'step'      => $step['id'],
            //     'value'     => $result
            // ));
            $step=$this->getNextStep($step,$params);
            $next=$this->db->query('select * from pathflow where step='.$step['id'].' and pathway='.$params['pathway'])->result_array();
            $data['step']=$step['id'];
            $data['back']=$next[0]['back'];
            $data['next']=$next[0]['next'];
            //echo '<pre>';print_r($step);print_r($data); exit;
            // $step=$this->getStepByNumber($data['next']);
            // $step=$this->getNextStep($step,$params);
            //echo '<pre>';print_r($step);print_r($data);exit;
            if($step['type']=='question')
            {
                echo "<script>console.log('106 next step is question')</script>";
                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$data['step'])->result_array();
                $data['question']=$st[0];
                return $data;
            }
            else
            {

                echo "<script>console.log('113 step ".$step['id']." not question')</script>";
                //print_r($data);exit;
                $url = 'selfcare/pq_view';
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
        
        if($step['type']=='question' || $step['type']=='info')
        {
            echo "<script>console.log('132 next step is question')</script>";
            return $data;
        }

        if($step['type']=='condition')
        {
            echo "<script>console.log('138 step ".$step['id']." is condition')</script>";
            $result=$params['score'];
            $st=$this->db->query('select * from step_condition where step='.$step['id'])->result_array();
            $condition=$st[0];
            //echo '<pre>';print_r($condition);exit;
            $d['step']=$condition['step_result'];
            $d['pathway']=$params['pathway'];
            $d['user_id']=$params['user_id'];

            $result=$this->getStepAnswer($d);
            //print_r($step['id'].'-'.$params['pathway']); exit;
            echo "<script>console.log('147 result is".$result['value']."')</script>";
            switch($condition['operator'])
            {
                case '>':
                    if($result['value'] > $condition['value'])
                    {
                        $data['step']=$condition['if_next_step'];
                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['id'];
                        $data['next']=$path['next'];
                        echo "<script>console.log('158 next step ".$data['next']."')</script>";
                    }
                    else
                    {
                        $data['step']=$condition['else_next_step'];  
                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['id']; 
                        $data['next']=$path['next'];
                        //echo '<pre>';print_r($path);exit;
                    }
                break;
                case '<':
                    if($condition['value'] < $result)
                    {
                        $data['step']=$condition['if_next_step'];
                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['id'];
                        $data['next']=$path['next'];
                    }
                    else
                    {
                        $data['step']=$condition['else_next_step'];  
                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['id']; 
                        $data['next']=$path['next'];
                    }
                break;
                case '=':
                    if($result == $condition['value'])
                    {
                        $data['step']=$condition['if_next_step'];
                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['id'];
                        $data['next']=$path['next'];
                    }
                    else
                    {
                        $data['step']=$condition['else_next_step'];  
                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['id']; 
                        $data['next']=$path['next'];
                    }
                break;
            }
            //echo '<pre>';print_r($step);print_r($data);exit;
            $step=$this->getStepByNumber($data['step']);
            //$step=$this->getNextStep($step,$params);
            //echo '<pre>';print_r($step);print_r($data);exit;

            if($step['type']=='question')
            {
                echo "<script>console.log('211 step ".$step['id']." is question')</script>";
                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$data['step'])->result_array();
                $data['question']=$st[0];
                return $data;
            }
            else
            {   
                echo "<script>console.log('218 next step ".$step['id']." not question')</script>";
                //echo '<pre>';print_r($data);exit;
                $url = 'selfcare/pq_view';
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
        if($step['type']=='age')
        {
            //echo 'In age';exit;
            echo "<script>console.log('237 next step ".$step['id']." is age')</script>";
            $result=$params['age'];
            $st=$this->db->query('select * from step_age where step='.$step['id'])->result_array();
            $condition=$st[0];
            
            //echo '<pre>';print_r($condition);exit;
            switch($condition['operator'])
            {
                case '>':
                    if($result > $condition['value'])
                    {
                        $data['step']=$condition['if_next_step'];

                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['id'];
                        $data['next']=$path['next'];
                        
                    }
                    else
                    {
                        $data['step']=$condition['else_next_step'];  
                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['id']; 
                        $data['next']=$path['next'];
                        //echo '<pre>';print_r($path);exit;
                    }
                break;
                
            }
            
            $step=$this->getStepByNumber($data['step']);
            echo "<script>console.log('289 next step ".$step['id']." is ".$step['type']."')</script>";
            $data['step']=$step['id'];
            // echo '<pre>';print_r($step);print_r($data);exit;
            //$step=$this->getNextStep($step,$params);
            
            
            if($step['type']=='question')
            {
                echo "<script>console.log('311 next step is question')</script>";
                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$data['step'])->result_array();
                $data['question']=$st[0];
                return $data;
            }
            else
            {
                echo "<script>console.log('317 next step ".$data['step']." not question it is ".$step['type']."')</script>";
                //echo '<pre>';print_r($data);exit;
                $url = 'selfcare/pq_view';
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
            echo "<script>console.log('Step ".$step['id']." is flag')</script>";
            $result=$params['score'];
            $st=$this->db->query('select * from step_flag where step='.$step['id'])->result_array();
            $condition=$st[0];
            
            //echo '<pre>';print_r($condition);exit;
            $this->session->set_userdata('flag','red');
            $data['step']=$condition['if_next_step'];
            $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'])->result_array();
            $path=$st[0];
            $data['back']=$step['id'];
            $data['next']=$path['next'];

            $step=$this->getStepByNumber($data['next']);
            //$step=$this->getNextStep($step,$params);
            //echo '<pre>';print_r($step);exit;
            echo "<script>console.log('369 Next Step ".$step['id']." is".$step['type']." ')</script>";
            if($step['type']=='question')
            {
                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$data['step'])->result_array();
                $data['question']=$st[0];
                return $data;
            }
            else
            {
                echo "<script>console.log('377 Next Step ".$data['step']." is not question')</script>";
                //echo '<pre>';print_r($data);exit;
                $url = 'selfcare/pq_view';
                $myvars = http_build_query($data, '', '&');

                $ch = curl_init( $url );
                curl_setopt( $ch, CURLOPT_POST, 1);
                curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

                curl_exec( $ch );
            }
        }
        if($step['type']=='gender')
        {
            //echo 'In age';exit;
            echo "<script>console.log('361 next step ".$step['id']." is gender')</script>";
            $result=$params['gender'];
            $st=$this->db->query('select * from step_gender where step='.$step['id'])->result_array();
            $condition=$st[0];
            
            //echo '<pre>';print_r($condition);exit;
            switch($condition['operator'])
            {
                case '>':
                    if($result > $condition['value'])
                    {
                        $data['step']=$condition['if_next_step'];

                        $st=$this->db->query('select * from pathflow where step='.$condition['if_next_step'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['id'];
                        $data['next']=$path['next'];
                    }
                    else
                    {
                        $data['step']=$condition['else_next_step'];  
                        $st=$this->db->query('select * from pathflow where step='.$condition['else_next_step'])->result_array();
                        $path=$st[0];
                        $data['back']=$step['id']; 
                        $data['next']=$path['next'];
                    }
                break;
                
            }
            
            $step=$this->getStepByNumber($data['step']);
            echo "<script>console.log('394 next step ".$step['id']." is ".$step['type']."')</script>";
            $data['step']=$step['id'];
            // echo '<pre>';print_r($step);print_r($data);exit;
            //$step=$this->getNextStep($step,$params);
            
            
            if($step['type']=='question')
            {
                echo "<script>console.log('402 next step is question')</script>";
                $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$data['step'])->result_array();
                $data['question']=$st[0];
                return $data;
            }
            else
            {
                echo "<script>console.log('409 next step ".$data['step']." not question it is ".$step['type']."')</script>";
                //echo '<pre>';print_r($data);exit;
                $url = 'selfcare/pq_view';
                $myvars = http_build_query($data, '', '&');

                $ch = curl_init( $url );
                curl_setopt( $ch, CURLOPT_POST, 1);
                curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

                curl_exec( $ch );
            }
        }
        if($step['type']=='formula')
        {
            echo 'In Formula';exit;
            $st=$this->db->select('*')->from('step_answers')
                ->where('pathway',$params['pathway'])
                ->where('step', $data['back'])
                ->where('field_name','weight')
                ->get()
                ->result_array();
            $weight=$st[0]['value'];
            $st=$this->db->select('*')->from('step_answers')
                ->where('pathway',$params['pathway'])
                ->where('step', $data['back'])
                ->where('field_name','height')
                ->get()
                ->result_array();
            $height=$st[0]['value'];
            $result=($weight)/(($height*$height)/10000);
            if($result<15)
            {
                $this->session->set_userdata('category','very severely underweight');
            }
            elseif($result >=15 && $result <=16)
            {
                $this->session->set_userdata('category','severely underweight');
            }     
            elseif($result >16 && $result <=18)
            {
                $this->session->set_userdata('category','underweight');
            } 
            elseif($result > 18 && $result <=25)
            {
                $this->session->set_userdata('category','normal (healthy weight)');
            } 
            elseif($result >25 && $result <=30)
            {
                $this->session->set_userdata('category','overweight');
            } 
            elseif($result > 30 && $result <=35)
            {
                $this->session->set_userdata('category','moderately obese');
            } 
            elseif($result >35 && $result <=40)
            {
                $this->session->set_userdata('category','severely obese');
            } 
            elseif($result > 40)
            {
                $this->session->set_userdata('category','very severely obese');
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
        $st=$this->db->select('*')->from('pathflow')
                ->where('pathway',$params['pathway'])
                ->where('step',$params['step'])
                ->where('next',$params['next'])
                ->get()->result_array();
        $data=$st[0];
        $st=$this->db->query('select questions.* from questions inner join step_questions on step_questions.question=questions.id where step='.$data['step'])->result_array();
        $data['question']=$st[0];
        //echo '<pre>';print_r($data); exit;
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
        return $st[0];
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

    public function getAnsForm($qId)
    {
        return $this->db->select('*')->from('ans_form')->where('question',$qId)->get()->result_array();
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
        return $this->db->query('SELECT steps.*, pathways.name as pathway from steps inner join pathways on pathways.id=steps.pathway ')->result_array();
    }

    public function getPathFlowSteps($id)
    {
        return $this->db->query('SELECT steps.*, pathways.name as pathway from steps inner join pathways on pathways.id=steps.pathway  where steps.pathway='.$id)->result_array();
    }

    public function getPathFlowByStep($id)
    {
        $st=$this->db->select('*')->from('pathflow')->where('step',$id)->get()
        ->result_array();
        return $st[0];
    }

    public function getStepByNumber($id)
    {
        $st=$this->db->select('*')->from('steps')->where('number',$id)->get()->result_array();
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
        //echo '<pre>';print_r($data);exit;
        $pth=$this->db->select('*')
                    ->from('user_pathway_status')
                    ->where('user_id',$data['user_id'])
                    ->where('pathway',$data['pathway'])
                    ->where('status','pending')
                    ->get()
                    ->result_array();
        if(count($pth)>0)
        {
            $item=array(
                'user_id'   =>  $data['user_id'],
                'pathway'   =>  $data['pathway'],
                'current_step'=>$data['step'],
                'status'    =>  'pending'
            );
            $this->db->where('id',$pth[0]['id'])->update('user_pathway_status',$item);
        }
        else
        {
            $item=array(
                'user_id'   =>  $data['user_id'],
                'pathway'   =>  $data['pathway'],
                'current_step'=>$data['step'],
                'status'    =>  'pending'
            );
            $this->db->insert('user_pathway_status',$item);
        }
        $st=$this->db->query('Select questions.* from questions inner join step_questions on step_questions.question=questions.id where step_questions.step='.$data['step'])->result_array();
        $am=$this->getAllById('answer_models',$st[0]['ans_model']);
        if($am['text']>0)
        {
            //echo $am['text'].' textboxes <br>';
            $ans_form=$this->getAnsForm($st[0]['id']);
            for($i=0;$i<count($ans_form);$i++)
            {
                $item=array(
                    'pathway'   => $data['pathway'],
                    'step'      => $data['step'],
                    'value'     => $data[$ans_form[$i]['name']],
                    'field_name'=>$ans_form[$i]['name'],
                    'user_id'   =>$data['user_id']
                );
                $pth=$this->db->select('*')
                            ->from('user_pathway_status')
                            ->where('user_id',$data['user_id'])
                            ->where('pathway',$data['pathway'])
                            ->where('status','pending')
                            ->get()
                            ->result_array();
                //echo '<pre> path';print_r($pth);exit;
                $st=$this->db->select('*')
                            ->from('step_answers')
                            ->where('step',$data['step'])
                            ->where('user_id',$data['user_id'])
                            ->where('created_at >',$pth[0]['started_at'])
                            ->get()
                            ->result_array();
                if(count($st)>0)
                {
                    
                    $this->db->where('step',$data['step'])
                            ->where('user_id',$data['user_id'])
                            ->where('created_at >',$pth[0]['started_at'])
                            ->update('step_answers',$item);
                }
                else
                {
                    
                    $this->db->insert('step_answers',$item);
                }
            }
            
        }
        if($am['radio']>0)
        {
            echo "<script>console.log('795 saving radio data for step ".$data['step']." and value is ".$data['score']."')</script>";
            $item=array(
                'pathway'   => $data['pathway'],
                'step'      => $data['step'],
                'value'     => $data['score'],
                'user_id'   =>$data['user_id']
            );
            // find answer whose created_at is bigger than status created_at or insert 
            $pth=$this->db->select('*')
                        ->from('user_pathway_status')
                        ->where('user_id',$data['user_id'])
                        ->where('pathway',$data['pathway'])
                        ->where('status','pending')
                        ->get()
                        ->result_array();
            //echo '<pre> path';print_r($pth);exit;
            $st=$this->db->select('*')
                        ->from('step_answers')
                        ->where('step',$data['step'])
                        ->where('user_id',$data['user_id'])
                        ->where('created_at >',$pth[0]['started_at'])
                        ->get()
                        ->result_array();

            //echo '<pre>';print_r($st);exit;
            // $st=$this->db->query('select * from step_answers where step='.$data['step'])->result_array();
            if(count($st)>0)
            {
                
                $this->db->where('step',$data['step'])
                        ->where('user_id',$data['user_id'])
                        ->where('created_at >',$pth[0]['started_at'])
                        ->update('step_answers',$item);
            }
            else
            {
                
                $this->db->insert('step_answers',$item);
            }
        }
        if($am['checkbox']>0)
        {
            echo "<script>console.log('886 saving checkbox data for step ".$data['step']."')</script>";
            $item=array(
                'pathway'   => $data['pathway'],
                'step'      => $data['step'],
                'user_id'   =>$data['user_id'],
                'value'     => implode(',', $data['score'])
            );

            $pth=$this->db->select('*')
                        ->from('user_pathway_status')
                        ->where('user_id',$data['user_id'])
                        ->where('pathway',$data['pathway'])
                        ->where('status','pending')
                        ->get()
                        ->result_array();
            //echo '<pre> path';print_r($pth);exit;
            $st=$this->db->select('*')
                        ->from('step_answers')
                        ->where('step',$data['step'])
                        ->where('user_id',$data['user_id'])
                        ->where('created_at >',$pth[0]['started_at'])
                        ->get()
                        ->result_array();
            if(count($st)>0)
            {
                
                $this->db->where('step',$data['step'])
                        ->where('user_id',$data['user_id'])
                        ->where('created_at >',$pth[0]['started_at'])
                        ->update('step_answers',$item);
            }
            else
            {
                
                $this->db->insert('step_answers',$item);
            }
        }
        

    }
    public function getStepByNumberPathway($step, $pathway)
    {
        $st=$this->db->select('*')->from('steps')->where('number',$step)->where('pathway', $pathway)->get()->result_array();
        return $st[0];
    }
    // Modified
    public function getStepAnswer($data)
    {
        echo "<script>console.log('Step:".$data['step']." pathway:".$data['pathway']."')</script>";
        $pth=$this->db->select('*')
                            ->from('user_pathway_status')
                            ->where('user_id',$data['user_id'])
                            ->where('pathway',$data['pathway'])
                            ->where('status','pending')
                            ->get()
                            ->result_array();
        
        $st=$this->db->select('*')
                            ->from('step_answers')
                            ->where('step',$data['step'])
                            ->where('user_id',$data['user_id'])
                            ->where('pathway', $data['pathway'])
                            ->where('created_at >',$pth[0]['started_at'])
                            ->get()
                            ->result_array();
        //echo '<pre> path';print_r($st);exit;
        //$st=$this->db->query('select * from step_answers where step='.$step.' and pathway='.$pathway)->result_array();
        echo "<script>console.log('query:".$this->db->last_query()." pathway:".$data['pathway']."')</script>";
        if(count($st)>0)
        {
            return $st[0];
        }
        else
        {
            return array();
        }
    }

    public function pathway_review($params)
    {
        $st=$this->db->select('*')
                        ->from('user_pathway_status')
                        ->where('user_id',$params['user_id'])
                        ->where('pathway',$params['pathway'])
                        ->where('status','finished')  
                        ->order_by('finished_at', 'DESC')                      
                        ->get()
                        ->result_array();
        $attempt=$st[0];
        $st=$this->db->select('*')
                        ->from('step_answers')
                        ->where("created_at BETWEEN '".$attempt['started_at']."' and '".$attempt['finished_at']."'")
                        ->where('user_id',$params['user_id'])
                        ->where('pathway', $params['pathway'])
                        ->get()
                        ->result_array();
        $data=array();
        // return $st;
        $count=count($st);
        for($i=0;$i<$count;$i++)
        {
            $step=$this->getStepByNumber($st[$i]['step']);
            $data[$i]['type']=$step['type'];
            $q=$this->getQuestionByStep($st[$i]['step']);
            $data[$i]['question']=$q['statement'];
            $data[$i]['answer']=$this->getAnswerResult($q['id'],$st[$i]['value']);
            // $data[$i]['answer']=$st[$i]['value'];

        }

        return $data;;
    }

    public function getAnswerResult($q, $v)
    {
        $st=$this->db->select('caption')
                    ->from('ans_form')
                    ->where('question', $q)
                    ->where('value',$v)
                    ->get()
                    ->result_array();
        return $st[0]['caption'];
    }

}