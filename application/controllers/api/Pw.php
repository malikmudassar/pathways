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
    public function pathways_post()
    {
        $user_id=$_REQUEST['user_id'];
        $pathways=$this->Admin_model->getUserPublishedPathways($user_id);
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
        $age=$_REQUEST['age'];
        
        $data=$this->Admin_model->getFirstPathwayQuestion($Id, $user_id, $age);
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
        
        $data['form']=$this->Admin_model->getAnsForm($data['question']['id'], $params);


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
        if($data['step']==1)
        {
            $this->Admin_model->finish_pw($Id, $user_id);
        }
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
        
        if(!isset($params['practice_id']))
        {
            $params['practice_id']=0;
        }       
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
            $this->Admin_model->flushPw($params);
        }
        if($data['pathway']==24 && $data['step']==11)
        {
            $data['step_type']='link';
            // $this->Admin_model->flushPw($params);
        }
        if($data['pathway']==23 && $data['step']==29)
        {
            $data['step_type']='link';
            // $this->Admin_model->flushPw($params);
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
        
        $step=$this->Admin_model->getBackStepByFlow($params);
        // echo '<pre>';print_r($step);exit;
        
        $params['step']=$step['number'];
        
        $data=$this->Admin_model->getBackPathwayQuestion1($params);
        
        // print_r($data);exit;
        $data['user_id']=$params['user_id'];
        $this->Admin_model->removeAnswers($data);
        $data['answer']=$this->Admin_model->getStepAnswer($params);
        if($params['pathway']==21 && $step['number']==11)
        {
            $data['answer']=array_reverse($data['answer']);
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
            $this->Admin_model->flushPw($params);
        }
        if($data['step']==1)
        {
            $this->Admin_model->finish_pw($params['pathway'], $params['user_id']);
        }
        if($data['pathway']==24 && $data['step']==11)
        {
            $data['step_type']='link';
        }
        if($data['pathway']==23 && $data['step']==29)
        {
            $data['step_type']='link';
        }
        $this->Admin_model->removeFlowStep($step['number'], $params['pathway'], $params['user_id']);
        ///////////////////////////////////////////////////////////////
        if ($data)
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
        $data['form']=$this->Admin_model->getAnsForm($data['question']['id'],$params);
        $data['answer']=array();
        $data['answer']=$this->Admin_model->getStepAnswer($params);
        
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
        $this->Admin_model->removeAnswers($data);
        // BMI Pathway, If step =1 , the step type should be dropdown
        // if($params['pathway']==3 && $params['step']==1)
        // {
        //     $data['step_type']='dropdown';
        // } 
        ///////////////////////////////////////////////////////////////
        
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
        $path=$_SERVER['DOCUMENT_ROOT'];
        shell_exec('php '.$path.'/pathways/test.php '.$id.' &');
        // $data['condition_key']=strtolower($this->Admin_model->getPathwayName($params['pathway']));
        // if($params['pathway']==25 && strtolower($params['gender'])=='male' )
        // {
        //     $data['condition_key']='bloodTestMale';
        // }
        // if($params['pathway']==25 && strtolower($params['gender'])=='female' )
        // {
        //     $data['condition_key']='bloodTestFemale';
        // }
        // if($params['pathway']==20 && strtolower($params['gender'])=='male')
        // {
        //     $data['condition_key']='sti-male';
        // }
        // if($params['pathway']==20 && strtolower($params['gender'])=='female')
        // {
        //     $data['condition_key']='sti-female';
        // }
        // if($params['pathway']==22)
        // {
        //     $data['condition_key']='chase-referrer';
        // }
        // if($params['pathway']==21)
        // {
        //     $data['condition_key']='sick-note';
        // }
        // if($params['pathway']==24)
        // {
        //     $data['condition_key']='order-medication';
        // }
        // if($params['pathway']==26)
        // {
        //     $data['condition_key']='general-advice';
        // }
        // $data['condition_schema']=$this->Admin_model->pathway_review_for_BS($params);
        $data2['code']='200';
        $data2['message']='Pathway submitted successfully';
        // $endpoint='v3/dr-iq/onboarding/pathway-save';
        // $url = 'https://qa-driq-server.attech-ltd.com/'.$endpoint;
        // //$url = 'https://stag-server.attech-ltd.com/'.$endpoint;
        // $myvars = http_build_query($data, '', '&');
        // $this->Admin_model->changeIsSubmittedStatus($params, 'yes');
        // $ch = curl_init( $url );
        // curl_setopt( $ch, CURLOPT_POST, 1);
        // curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
        // curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
        // curl_setopt( $ch, CURLOPT_HEADER, 0);
        // curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

        // curl_exec( $ch );
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
    public function submit_pwT_post()
    {
        // $params=$_REQUEST;
        $data=$_REQUEST;
        $data['message']='Pathway Submitted successfully';
        // print_r($data);exit;
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

    public function test_post()
    {
        $pw=26;
        $user_id=101;
        $path=$_SERVER['DOCUMENT_ROOT'];
        shell_exec('php '.$path.'/pathways/test.php '.$pw.' >> paging.log &');
        $data2['code']='200';
        $data2['message']='Pathway submitted successfully';
        if($data2)
        {
            // Set the response and exit
            $this->response($data2, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
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
