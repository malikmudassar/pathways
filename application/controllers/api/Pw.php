<?php
use Restserver\Libraries\REST_Controller;
defined('BASEPATH') OR exit('No direct script access allowed');

// This can be removed if you use __autoload() in config.php OR use Modular Extensions
/** @noinspection PhpIncludeInspection */
//To Solve File REST_Controller not found
require APPPATH . 'libraries/REST_Controller.php';
require APPPATH . 'libraries/Format.php';

/**
 * This is an example of a few basic user interaction methods you could use
 * all done with a hardcoded array
 *
 * @package         CodeIgniter
 * @subpackage      Rest Server
 * @category        Controller
 * @author          Phil Sturgeon, Chris Kacerguis
 * @license         MIT
 * @link            https://github.com/chriskacerguis/codeigniter-restserver
 */
class Pw extends REST_Controller {

    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('Admin_model');
    }

    // GET request for pathways list [not used]
    public function pathways_get()
    {
        $pathways=$this->Admin_model->getPublishedPathways();
        if ($pathways)
            {
                // Set the response and exit
                $this->response($pathways, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
            }
            else
            {
                // Set the response and exit
                $this->response([
                    'status' => FALSE,
                    'message' => 'No pathways were found'
                ], REST_Controller::HTTP_NOT_FOUND); // NOT_FOUND (404) being the HTTP response code
            }
    }
    /*    
        Post request for listing pathways
        @Params
            user_id
            practice_id
        return JSON
    */
    public function pathways_post()
    {
        // $req_dump = print_r($_REQUEST, true);
        // $fp = file_put_contents('/var/log/apache2/access.log', $req_dump, FILE_APPEND);
        $user_id=$_REQUEST['user_id'];
        if($_SERVER['SERVER_NAME']=='pathways.dr-iq.com')
        {
            $url='https://server.attech-ltd.com/v3/dr-iq/onboarding/allowed-pathways';
        }
        elseif($_SERVER['SERVER_NAME']=='stag-pathways.dr-iq.com')
        {
            $url='https://stag-server.attech-ltd.com/v3/dr-iq/onboarding/allowed-pathways';
        }
        elseif($_SERVER['SERVER_NAME']=='dev-pathways.dr-iq.com')
        {
            $url='https://dev-driq-server.attech-ltd.com/v3/dr-iq/onboarding/allowed-pathways';
        }
        else
        {
            $url='https://qa-driq-server.attech-ltd.com/v3/dr-iq/onboarding/allowed-pathways';
        }
        
        $myvars=array();
        $myvars['organization_id']= $_REQUEST['practice_id'];               
        $ch = curl_init( $url );
        curl_setopt( $ch, CURLOPT_POST, 1);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt( $ch, CURLOPT_HEADER, 0);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

        $pws=json_decode(curl_exec( $ch ));
        // print_r((array)$pws);exit;
        $req_dump = print_r($pws, true);
        // file_put_contents('/var/log/apache2/access.log', $req_dump, FILE_APPEND);
        $pathways=$this->Admin_model->getUserPermittedPathways((array)$pws);
        // $pathways=$this->Admin_model->getUserPublishedPathways($user_id);
        if ($pathways)
        {
            // Set the response and exit
            $this->response($pathways, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        }
        else
        {
            // Set the response and exit
            $this->response([
                'status' => FALSE,
                'message' => 'No pathways were found'
            ], REST_Controller::HTTP_NOT_FOUND); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function init_pw_get()
    {
        $this->session->set_userdata('flag','white');
        $Id=$_REQUEST['pw'];
        $params['pathway']=$Id;
        $params['gender']=strtolower($_REQUEST['gender']);
        $params['age']=$_REQUEST['age'];
        $data=$this->Admin_model->getFirstPathwayQuestion($Id);
        $data['form']=$this->Admin_model->getAnsForm($data['question']['id'], $params);

        if(!empty($data['form']))
        {
            $data['step_type']=$data['form'][0]['type'];  
            if($Id==3)
            {
                for($i=0;$i<count($data['form']);$i++)
                {
                    $data['form'][$i]['type']='number';
                    $data['form'][$i]['max']=5;
                }
            }  
        }
        else
        {
            $data['step_type']='info';
            $data['form']="";
        }
        if($data['step_type']=='textarea')
        {
            $data['step_type']='text';
        }
        if($data['pathway']==24 && $data['step']==13)
        {
            $data['step_type']='add_medication';
        }

        $data['percent']=number_format(0,2);
        if ($data['question'])
        {
            // Set the response and exit
            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        }
        else
        {
            // Set the response and exit
            $this->response([
                'status' => FALSE,
                'message' => 'Pathway doesn\'t have steps',
            ], REST_Controller::HTTP_NOT_FOUND); // NOT_FOUND (404) being the HTTP response code
        }
        
    }

    public function init_pw_post()
    {
        $this->session->set_userdata('flag','white');
        $Id=$_REQUEST['pw'];
        $user_id=$_REQUEST['user_id'];

        //-----------------------------------------
        $this->Admin_model->flush_pw_results($user_id, $Id);
        $this->Admin_model->flush_pw_steps($Id, $user_id); 
        //-----------------------------------------

        $age=$_REQUEST['age'];
        $params=$_REQUEST;
        $data=$this->Admin_model->getFirstPathwayQuestion($Id, $user_id, $age);
        $data['user_id']=$user_id;
        $params['pathway']=$Id;
        $params['gender']=strtolower($_REQUEST['gender']);
        if(!isset($params['gender']))
        {
            $params['gender']='Male';
        }
        else
        {
            $params['gender']=strtolower($_REQUEST['gender']);
        }
        $data['pname']=$this->Admin_model->getPathwayName($Id);
        $data['answer']=$this->Admin_model->getStepAnswer($data);
        $data['form']=$this->Admin_model->getAnsForm($data['question']['id'], $params);
        $this->Admin_model->removeFlowStep($data['step'], $data['pathway'], $data['user_id']); 
        // print_r($data);exit;
        if($params['pathway']==21 && $step['number']==22)
        {
            $d=array();
            $d[0]=$data['answer'][1];
            $d[1]=$data['answer'][2];
            $d[2]=$data['answer'][0];
            $data['answer']=$d;
        }
        if($data['pathway']==21 && $data['step']==11)
        {
            $data['answer']=array_reverse($data['answer']);
        }

        if(!empty($data['form']))
        {
            $data['step_type']=$data['form'][0]['type'];  
            if($Id==3 && $data['step']==1)
            {
                for($i=0;$i<count($data['form']);$i++)
                {
                    $data['form'][$i]['type']='dropdown';
                    $data['form'][$i]['max']=5;
                }
                $data['percent']=(int)$data['percent'];
            }  
        }
        else
        {
            $data['step_type']='info';
            $data['form']=array();
        }
        // BMI Pathway, If step =1 , the step type should be dropdown
        if($Id==3 && $data['step']==1)
        {
            $data['step_type']='dropdown';
            $data['step']='1';
            $data['back']='0';
            $data['next']='2';
        } 
        ///////////////////////////////////////////////////////////////
        if(!$data['percent'])
        {            
            $data['percent']=0;
        }
        // if($data['step']==1)
        // {
        //     $this->Admin_model->finish_pw($Id, $user_id);
        // }
        if($data['back']==0)
        {
            $this->Admin_model->finish_pw($Id, $user_id);
        }
        if($data['pathway']==24 && $data['step']==11)
        {
            $data['step_type']='link';
        }
        if($data['pathway']==24 && $data['step']==13)
        {
            $data['step_type']='add_medication';
        }
        if($data['pathway']==24 && $data['step']==8)
        {
            $data['step_type']='add_medication';
        }
        if($data['pathway']==23 && $data['step']==29)
        {
            $data['step_type']='link';
        }
        if($data['pathway']==23 && ($data['step']==29 || $data['step']==35 || $data['step']==45 || $data['step']==51 || $data['step']==66 || $data['step']==72 || $data['step']==82 || $data['step']==88))
        {
            $data['step_type']='link';
        }
        if($data['pathway']==17 && $data['step']==32)
        {
            $data['step_type']='link';
        }
        if($data['step_type']=='textarea')
        {
            $data['step_type']='text';
        }
        if($data['pathway']==21 && $data['step']==22)
        {
            $data['step_type']='text';
        }
        if($data['pathway']==21 && $data['step']==11)
        {
            $data['step_type']='text';
        }
        if($data['pathway']==23 && ($data['step']==27 || $data['step']==33 || $data['step']==43 || $data['step']==49 || $data['step']==64 || $data['step']==70 || $data['step']==80 || $data['step']==86))
        {
            for($i=0;$i<count($data['form']);$i++)
            {
                $data['form'][$i]['type']='dropdown';
            }
            $data['step_type']='dropdown';
        }
        $data['pathway']=$Id;
        // print_r($data);exit;
        if ($data['question'])
        {
            // Set the response and exit
            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        }
        else
        {
            // Set the response and exit
            $this->response([
                'status' => FALSE,
                'message' => 'Pathway doesn\'t have steps',
            ], REST_Controller::HTTP_NOT_FOUND); // NOT_FOUND (404) being the HTTP response code
        }
        
    }

    public function next_pw_post()
    {

        $params=$_REQUEST;
        // print_r($params);exit;
        $Id=$_REQUEST['pw'];
        $data['user_id']=$params['user_id'];
        if(!isset($params['practice_id']))
        {
            $params['practice_id']=2;
        }       
        // if($params['step']==1 && $Id != 17)
        // {
        //     $this->Admin_model->flushPw($params);
        // }
        $this->Admin_model->saveResult($params);
        //$name=$this->Admin_model->getPathwayName($params['pathway']);
        
        // $this->Admin_model->removeAnswers($params);
        if(!$params['age'])
        {
            $params['age']=21;
        }
        if(!$params['gender'])
        {
            $params['gender']='male';
        }
        else
        {
            $params['gender']=strtolower($_REQUEST['gender']);
        }
        //echo '<pre>';print_r($_POST);exit;
        $data=$this->Admin_model->getNextPathwayQuestion($params);

        $data['form']=$this->Admin_model->getAnsForm($data['question']['id'], $params);
        
        // echo '<pre>';print_r($data['form']);exit;
        if(!empty($data['form']))
        {
            $data['step_type']=$data['form'][0]['type'];
            $data['max_limit'] = $data['form'][0]['max_limit'];
        }
        else
        {
            $data['step_type']='info';
        }
        
        //echo '<pre>';print_r($data);exit;
        
        if($data['next']==0)
        {
            $data['percent']=100;
            $p=array(
                'user_id'   => $params['user_id'],
                'pathway'   => $params['pathway']
            );
            $this->Admin_model->savePercent($p);
        }
        if($data['pathway']==24 && $data['step']==16)
        {
            $data['step_type']='redirect';
            // print_r($data);exit;
            // $this->Admin_model->flushPw($data);
        }
        if($data['pathway']==24 && $data['step']==11)
        {
            $data['step_type']='link';
        }
        if($data['pathway']==24 && $data['step']==13)
        {
            $data['step_type']='add_medication';
        }
        if($data['pathway']==24 && $data['step']==8)
        {
            $data['step_type']='add_medication';
        }
        if($data['pathway']==29 && $data['step']==19)
        {
            $data['step_type']='add_medication';
        }
        if($data['pathway']==23 && $data['step']==29)
        {
            $data['step_type']='link';
        }
        if($data['pathway']==23 && ($data['step']==29 || $data['step']==35 || $data['step']==45 || $data['step']==51 || $data['step']==66 || $data['step']==72 || $data['step']==82 || $data['step']==88))
        {
            $data['step_type']='link';
        }
        if($data['pathway']==17 && $data['step']==32)
        {
            $data['step_type']='link';
        }
        if($data['step_type']=='textarea')
        {
            $data['step_type']='text';
        }
        if($data['pathway']==23 && ($data['step']==27 || $data['step']==33 || $data['step']==43 || $data['step']==49 || $data['step']==64 || $data['step']==70 || $data['step']==80 || $data['step']==86))
        {
            for($i=0;$i<count($data['form']);$i++)
            {
                $data['form'][$i]['type']='dropdown';
            }
            $data['step_type']='dropdown';
        }
        $data['pathway']=$params['pathway'];
        $data['pname']=$this->Admin_model->getPathwayName($data['pathway']);
        if($data['back']==0)
        {
            $this->Admin_model->finish_pw($params['pathway'], $params['user_id']);
            $this->Admin_model->flushPw($params);
        }
        if ($data)
        {
            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        }
        elseif(!$params['user_id'])
        {
            // Set the response and exit
            $this->response([
                'status' => FALSE,
                'message' => 'User ID not received on server',
            ], REST_Controller::HTTP_NOT_FOUND); // NOT_FOUND (404) being the HTTP response code
        }
        else
        {
            // Set the response and exit
            $this->response([
                'status' => FALSE,
                'message' => 'Pathway doesn\'t have steps',
            ], REST_Controller::HTTP_NOT_FOUND); // NOT_FOUND (404) being the HTTP response code
        }
        
    }
    public function back_pw_post()
    {

        $params=$_REQUEST;
        // print_r($params);exit;
        if($params['pathway']==24 && $params['step']==2)
        {
            // echo 'in here';exit;
            $this->Admin_model->removeFlowStep(2, $params['pathway'], $params['user_id']);
        }
        $step=$this->Admin_model->getBackStepByFlow($params);
        // echo '<pre>';print_r($step);exit;
        
        $params['step']=$step['number'];
        
        $data=$this->Admin_model->getBackPathwayQuestion1($params);
        
        // print_r($data);exit;
        $data['user_id']=$params['user_id'];
        // $this->Admin_model->removeAnswers($data);
        $data['answer']=$this->Admin_model->getStepAnswer($params);
        if($params['pathway']==21 && $step['number']==22)
        {
            $d=array();
            $d[0]=$data['answer'][1];
            $d[1]=$data['answer'][2];
            $d[2]=$data['answer'][0];
            $data['answer']=$d;
        }
        if($data['pathway']==21 && $data['step']==11)
        {
            $data['answer']=array_reverse($data['answer']);
        }
        // print_r($data['answer']);exit;
        if($params['pathway']==24 && $step['number']==10)
        {
            $data['answer'] = array_reverse($data['answer']);
            $valueencoded   = '';
            $count          = 1;
            if(!empty($data['answer']))
            {
                foreach($data['answer'] as $aRow)
                {
                    $value = $aRow['value'];
                    $path = '/var/www/html/pathways/img/';
                    $img_path = $path.$value;
                    $valueencoded .= base64_encode(file_get_contents($path.$value));

                    if($count != count($data['answer'])){
                        $valueencoded .= ',';
                    }
                    $count++;
                }
            }
            $data['answer'][0]['value'] = $valueencoded;

            // $value=$data['answer'][0]['value'];
            // // echo $value;exit;
            // $path='/var/www/html/pathways/img/';
            // $img_path = $path.$value;
            // // echo $img_path;exit;
            // $valueencoded=base64_encode(file_get_contents($path.$value));
            // $data['answer'][0]['value']=$valueencoded;
        }        
        if($params['pathway']==17 && $step['number']==52)
        {
            $data['answer'] = array_reverse($data['answer']);
            $valueencoded   = '';
            $count          = 1;
            if(!empty($data['answer'])){
                foreach($data['answer'] as $aRow){

                    $value = $aRow['value'];
                    $path = '/var/www/html/pathways/img/';
                    $img_path = $path.$value;
                    $valueencoded .= base64_encode(file_get_contents($path.$value));

                    if($count != count($data['answer'])){
                        $valueencoded .= ',';
                    }
                    $count++;
                }
            }
            $data['answer'][0]['value'] = $valueencoded;

            // $value=$data['answer'][0]['value'];
            // // echo $value;exit;
            // $path='/var/www/html/pathways/img/';
            // $img_path = $path.$value;
            // // echo $img_path;exit;
            // $valueencoded=base64_encode(file_get_contents($path.$value));
            // $data['answer'][0]['value']=$valueencoded;
        }
        // print_r($data['answer']);exit;
        $data['form']=array();
        $data['form']=$this->Admin_model->getAnsForm($data['question']['id'], $params);

        if(!empty($data['form']))
        {
            $data['step_type']=$data['form'][0]['type'];
            if($params['pathway']==3 && $params['step']==1)
            {
                for($i=0;$i<count($data['form']);$i++)
                {
                    $data['form'][$i]['type']='dropdown';
                }
            } 
        }
        else
        {
            $data['step_type']='info';
            $data['form']=array();
        }
        if($data['back']==0)
        {
            $data['percent']=0;
        }
        $data['user_id']=$params['user_id'];
        $data['step']=$params['step'];
        $this->Admin_model->updateStats($data);
        $st=$this->Admin_model->getStats($data);
      
        $data['percent']=(int)$st['percent'];
        if($data['pathway']==24 && $data['step']==16)
        {
            $data['step_type']='redirect';
            // $this->Admin_model->flushPw($data);
        }
        if($data['back']==0)
        {
            $this->Admin_model->removeRestSteps($params['pathway'], $params['user_id'], $data['step']);
        }
        
        if($data['pathway']==24 && $data['step']==11)
        {
            $data['step_type']='link';
        }
        if($data['pathway']==24 && $data['step']==13)
        {
            $data['step_type']='add_medication';
        }
        if($data['pathway']==24 && $data['step']==8)
        {
            $data['step_type']='add_medication';
        }
        if($data['pathway']==29 && $data['step']==19)
        {
            $data['step_type']='add_medication';
        }
        if($data['pathway']==23 && $data['step']==29)
        {
            $data['step_type']='link';
        }
        if($data['pathway']==17 && $data['step']==32)
        {
            $data['step_type']='link';
        }
        if($data['pathway']==23 && ($data['step']==29 || $data['step']==35 || $data['step']==45 || $data['step']==51 || $data['step']==66 || $data['step']==72 || $data['step']==82 || $data['step']==88))
        {
            $data['step_type']='link';
        }
        if($data['pathway']==21 && $data['step']==22)
        {
            $data['step_type']='text';
        }
        if($data['pathway']==21 && $data['step']==11)
        {
            $data['step_type']='text';
        }
        if($data['step_type']=='textarea')
        {
            $data['step_type']='text';
        }
        if($data['pathway']==23 && ($data['step']==27 || $data['step']==33 || $data['step']==43 || $data['step']==49 || $data['step']==64 || $data['step']==70 || $data['step']==80 || $data['step']==86))
        { //[27,33,43,49,64,70,80,86]
            for($i=0;$i<count($data['form']);$i++)
            {
                $data['form'][$i]['type']='dropdown';
            }
            $data['step_type']='dropdown';
        }
        $data['pathway']=$params['pathway'];
        $data['pname']=$this->Admin_model->getPathwayName($data['pathway']);
        $this->Admin_model->removeFlowStep($step['number'], $params['pathway'], $params['user_id']);
        ///////////////////////////////////////////////////////////////
        if ($data)
        {
            // print_r($data);die;
            // Set the response and exit
            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        }
        else
        {
            // Set the response and exit
            $this->response([
                'status' => FALSE,
                'message' => 'Pathway doesn\'t have steps',
            ], REST_Controller::HTTP_NOT_FOUND); // NOT_FOUND (404) being the HTTP response code
        }
        
    }

    public function pathway_preview_post()
    {
        $params=$_REQUEST;
        $data=$this->Admin_model->pathway_review($params);
        
        if($data)
        {
            // Set the response and exit
            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        }
        else
        {
            // Set the response and exit
            $this->response([
                'status' => FALSE,
                'message' => 'Pathway doesn\'t have steps',
            ], REST_Controller::HTTP_NOT_FOUND); // NOT_FOUND (404) being the HTTP response code
        }

    }
    // Edit question from summary 
    public function edit_q_post()
    {
        $params=$_REQUEST;
        $data=$this->Admin_model->getEditedQuestion($params);
        $this->Admin_model->removeNextStepsfromPathwaySteps($params);
        $data['form']=$this->Admin_model->getAnsForm($data['question']['id'],$params);
        $data['answer']=array();
        $data['answer']=$this->Admin_model->getStepAnswer($params);
        if($params['pathway']==21 && $params['step']==22)
        {
            $d=array();
            $d[0]=$data['answer'][1];
            $d[1]=$data['answer'][2];
            $d[2]=$data['answer'][0];
            $data['answer']=$d;
        }
        if($params['pathway']==21 && $params['step']==11)
        {
            $d=array();
            $d[0]=$data['answer'][2];
            $d[1]=$data['answer'][1];
            $d[2]=$data['answer'][0];
            $data['answer']=$d;
        }
        // print_r($data['answer']);exit;
        $path=$this->Admin_model->getPathFlowByStep($params['step'],$params['pathway']);
        $data['step']=$path['step'];
        $data['back']=$path['back'];
        $data['next']=$path['next'];
        if(!empty($data['form']))
        {
            $data['step_type']=$data['form'][0]['type'];
        }
        else
        {
            $data['step_type']='info';
            $data['form']=array();
        }
        $steps=$this->Admin_model->countPathwaySteps($params['pathway']);
        $data['percent']=($params['step']/$steps)*100;
        $data['user_id']=$params['user_id'];
        $data['pathway']=$params['pathway'];
        $data['step']=$params['step'];
        $this->Admin_model->updateStats($data);
        $st=$this->Admin_model->getStats($data);
        // BMI Pathway, If step =1 , the step type should be dropdown
        // if($params['pathway']==3 && $params['step']==1)
        // {
        //     $data['step_type']='dropdown';
        // } 
        ///////////////////////////////////////////////////////////////
        if($data['step_type']=='textarea')
        {
            $data['step_type']='text';
        }
        if($data['pathway']==24 && $data['step']==11)
        {
            $data['step_type']='link';
        }
        if($data['pathway']==24 && $data['step']==13)
        {
            $data['step_type']='add_medication';
        }
        if($data['pathway']==24 && $data['step']==8)
        {
            $data['step_type']='add_medication';
        }
        if($data['pathway']==23 && $data['step']==29)
        {
            $data['step_type']='link';
        }
        if($data['pathway']==23 && ($data['step']==29 || $data['step']==35 || $data['step']==45 || $data['step']==51 || $data['step']==66 || $data['step']==72 || $data['step']==82 || $data['step']==88))
        {
            $data['step_type']='link';
        }
        if($data['pathway']==21 && $data['step']==22)
        {
            $data['step_type']='text';
        }
        if($data['pathway']==21 && $data['step']==11)
        {
            $data['step_type']='text';
        }
        $data['pname']=$this->Admin_model->getPathwayName($data['pathway']);
        if($data)
        {
            // Set the response and exit
            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        }
        else
        {
            // Set the response and exit
            $this->response([
                'status' => FALSE,
                'message' => 'Pathway doesn\'t have steps',
            ], REST_Controller::HTTP_NOT_FOUND); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function submit_pw_post()
    {
        $params=$_REQUEST;

        $data['source']='obServer';
        $data['platform']='ob';
        $data['user_id']=$params['user_id'];
        $data['organization_id']=$params['practice_id'];
        $id=$this->Admin_model->getPathwayStatusId($params);
        // echo $id;exit;
        // $path=$_SERVER['DOCUMENT_ROOT'];
        // $path='http://'.$_SERVER['SERVER_NAME'].'/pathways/index.php/';
        // shell_exec('wget '.$path.'/api/pw/testPathwaySubmit/'.$params['user_id'].'/'.$params['practice_id'].'/'.$params['pathway'].' &');
        $data['source']='obServer';
        $data['platform']='ob';
        $data['user_id']=$params['user_id'];
        $data['organization_id']=$params['practice_id'];
        $data['condition_key']=strtolower($this->Admin_model->getPathwayName($params['pathway']));
        if($params['pathway']==25 && strtolower($params['gender'])=='male' )
        {
            $data['condition_key']='bloodTestMale';
        }
        if($params['pathway']==25 && strtolower($params['gender'])=='female' )
        {
            $data['condition_key']='bloodTestFemale';
        }
        if($params['pathway']==20 && strtolower($params['gender'])=='male')
        {
            $data['condition_key']='sti-male';
        }
        if($params['pathway']==20 && strtolower($params['gender'])=='female')
        {
            $data['condition_key']='sti-female';
        }
        if($params['pathway']==22)
        {
            $data['condition_key']='chase-referrer';
        }
        if($params['pathway']==21)
        {
            $data['condition_key']='sick-note';
        }
        if($params['pathway']==24)
        {
            $data['condition_key']='order-medication';
        }
        if($params['pathway']==26)
        {
            $data['condition_key']='general-advice';
        }
        if($params['pathway']==27)
        {
            $data['condition_key']='request-a-report';
        }
        if($params['pathway']==29)
        {
            $data['condition_key']='asthma';
        }
        $data['condition_schema']=$this->Admin_model->pathway_review_for_BS($params);
        // print_r($data);exit;
        $endpoint='v3/dr-iq/onboarding/pathway-save';
        if($_SERVER['SERVER_NAME']=='pathways.dr-iq.com')
        {
            $url = 'https://server.attech-ltd.com/'.$endpoint;
            
        }
        elseif($_SERVER['SERVER_NAME']=='stag-pathways.dr-iq.com')
        {
            $url = 'https://stag-server.attech-ltd.com/'.$endpoint;
            
        }
        elseif($_SERVER['SERVER_NAME']=='dev-pathways.dr-iq.com')
        {
            $url='https://dev-driq-server.attech-ltd.com/'.$endpoint;
        }
        else
        {
            $url = 'https://qa-driq-server.attech-ltd.com/'.$endpoint;
        }
        
        $myvars = http_build_query($data, '', '&');
        $this->Admin_model->changeIsSubmittedStatus($params, 'yes');
        $ch = curl_init( $url );
        curl_setopt( $ch, CURLOPT_POST, 1);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt( $ch, CURLOPT_HEADER, 0);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

        $slot=json_decode(curl_exec( $ch ));
        // print_r(($slot));exit;
        if($slot->slot_id)
        {
            $this->Admin_model->insertSlotId($slot->slot_id, $params['user_id'], $params['pathway']);
        }        

        $data2['code']='200';
        $data2['message']='Your request for online consultation has been submitted successfully';
        $data2['slotId']=$this->Admin_model->getLastInsertedSlotId($params['user_id'],$params['pathway']);
        // $data2['slotId']=($slot->slot_id)?$slot->slot_id:3955;
        $this->Admin_model->finish_pw($params['pathway'], $params['user_id']);
        if($data2)
        {
            // Set the response and exit
            $this->response($data2); // OK (200) being the HTTP response code
        }
        else
        {
            // Set the response and exit
            $this->response([
                'status' => FALSE,
                'message' => 'Pathway doesn\'t have steps',
            ], REST_Controller::HTTP_NOT_FOUND); // NOT_FOUND (404) being the HTTP response code
        }
    }
    
    public function submit_pathway_post()
    {
        $params=$_REQUEST;
        
        $data['user_id']=$params['user_id'];
        $data['organization_id']=$params['practice_id'];
        $id=$this->Admin_model->getPathwayStatusId($params);
        $path=$_SERVER['DOCUMENT_ROOT'];
        shell_exec('php '.$path.'/pathways/test.php '.$id.' &');
        $data['status']='200';
        $data['message']='Pathway submitted successfully';
        $data['id']=$id;
        if($data)
        {
            // Set the response and exit
            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        }
        else
        {
            // Set the response and exit
            $this->response([
                'status' => FALSE,
                'message' => 'Pathway doesn\'t have steps',
            ], REST_Controller::HTTP_NOT_FOUND); // NOT_FOUND (404) being the HTTP response code
        }
    }


    public function getPathwayName()
    {
        $pw=$this->uri->segment(3);
        $pname=$this->Admin_model->getPathwayName($pw);
        echo $pname;
    }

    public function pathway_review_for_BS_get()
    {
        $params['user_id']=$this->uri->segment(4);
        $params['pathway']=$this->uri->segment(5);
        $data['condition_schema']=$this->Admin_model->pathway_review_for_BS($params);
        print_r($data['condition_schema']);
    }

    

}
