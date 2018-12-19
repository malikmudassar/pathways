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

        // Configure limits on our controller methods
        // Ensure you have created the 'limits' table and enabled 'limits' within application/config/rest.php
        $this->methods['users_get']['limit'] = 500; // 500 requests per hour per user/key
        $this->methods['users_post']['limit'] = 100; // 100 requests per hour per user/key
        $this->methods['users_delete']['limit'] = 50; // 50 requests per hour per user/key
        $this->load->model('Admin_model');
    }


    public function pathways_get()
    {
        $pathways=$this->Admin_model->getAll('pathways');
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
        //echo '<pre>';print_r($_REQUEST);exit;
        $this->Admin_model->saveResult($params);
        //echo '<pre>';print_r($_POST);exit;
        $data['question']=$this->Admin_model->getNextPathwayQuestion($params);
        $data['form']=$this->Admin_model->getAnsForm($data['question']['question']['id']);
        //echo '<pre>';print_r($data);exit;
        
        
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


}
