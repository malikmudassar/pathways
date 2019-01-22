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
        
        $data=$this->Admin_model->getFirstPathwayQuestion($Id);
        $data['form']=$this->Admin_model->getAnsForm($data['question']['id']);

        if(!empty($data['form']))
        {
            $data['step_type']=$data['form'][0]['type'];  
            if($Id==3)
            {
                for($i=0;$i<count($data['form']);$i++)
                {
                    $data['form'][$i]['type']='number';
                    $data['form'][$i]['max']=4;
                }
            }  
        }
        else
        {
            $data['step_type']='info';
            $data['form']="";
        }

        $data['percent']=0;
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
        
        $data=$this->Admin_model->getFirstPathwayQuestion($Id, $user_id);
        
        $data['form']=$this->Admin_model->getAnsForm($data['question']['id']);


        if(!empty($data['form']))
        {
            $data['step_type']=$data['form'][0]['type'];  
            if($Id==3)
            {
                for($i=0;$i<count($data['form']);$i++)
                {
                    $data['form'][$i]['type']='decimal';
                    $data['form'][$i]['max']=4;
                }
            }  
        }
        else
        {
            $data['step_type']='info';
            $data['form']="";
        }

        if(!$data['percent'])
        {            
            $data['percent']=0;
        }
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
        
        //
        //echo '<pre>';print_r($_REQUEST);exit;
        if($params['step']==1)
        {
            $this->Admin_model->flush_pw_results($params['user_id'],$params['pathway']);
        }
        $this->Admin_model->saveResult($params);
        if(!$params['age'])
        {
            $params['age']=21;
        }
        if(!$params['gender'])
        {
            $params['gender']='male';
        }
        //echo '<pre>';print_r($_POST);exit;
        $data=$this->Admin_model->getNextPathwayQuestion($params);

        $data['form']=$this->Admin_model->getAnsForm($data['question']['id'], $params);
        
        //echo '<pre>';print_r($data['form']);exit;
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
        if ($data)
        {
            // Set the response and exit
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
        // $params['pathway']=$this->uri->segment(3);
        // $params['step']=$this->uri->segment(4);
        // $params['next']=$this->uri->segment(5);
        //echo '<pre>';print_r($params);exit;
        $step=$this->Admin_model->getStepByNumber($params['step'], $params['pathway']);
        // echo '<pre>';print_r($step);exit;
        if($step['type']!='question' && $step['type']!='info')
        {
            do {
                $path=$this->Admin_model->getPathFlowByStep($step['number'], $params['pathway']);
                // print_r($path);exit;
                $step=$this->Admin_model->getStepByNumber($path['back'], $params['pathway']);
                $path=$this->Admin_model->getPathFlowByStep($step['number'], $params['pathway']);
            }while($step['type']!='question');
            
            $params['step']=$path['step'];
            $params['next']=$path['next'];
            
        }
        // print_r($params);exit;
        $data=$this->Admin_model->getBackPathwayQuestion($params);
        if($params['pathway']==3)
        {
            $data['answer']=$this->Admin_model->getStepAnswer($params);
        }
        else
        {
            $data['answer'][0]=$this->Admin_model->getStepAnswer($params);
            if(!$data['answer'][0])
            {
                $data['answer'][0]=(object)array();
            }
        }
         
        // print_r($data['answer']);exit;
        
        $data['form']=$this->Admin_model->getAnsForm($data['question']['id'], $params);

        if(!empty($data['form']))
        {
            $data['step_type']=$data['form'][0]['type'];
            if($params['pathway']==3)
            {
                for($i=0;$i<count($data['form']);$i++)
                {
                    $data['form'][$i]['type']='number';
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


}
