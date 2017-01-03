<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt New BSD License
 */

/**
 * @author Hossein Azizabadi <azizabadi@faragostaresh.com>
 */

namespace Module\Support\Controller\Admin;

use Pi;
use Pi\Mvc\Controller\ActionController;
use Pi\Paginator\Paginator;
use Pi\File\Transfer\Upload;
use Zend\Db\Sql\Predicate\Expression;
use Module\Support\Form\TicketForm;
use Module\Support\Form\TicketFilter;
use Module\Support\Form\StatusForm;
use Module\Support\Form\StatusFilter;
use Module\Support\Form\SearchForm;
use Module\Support\Form\SearchFilter;

class TicketController extends ActionController
{
    public function indexAction()
    {
        // Get page
        $page = $this->params('page', 1);
        $searchStatus = $this->params('searchStatus', 'open');
        $searchUser = $this->params('searchUser');
        $searchLabel = $this->params('searchLabel');
        // Set info
        $ticket = array();
        $where = array('mid' => 0);
        $order = array('time_update DESC', 'id DESC');
        $offset = (int)($page - 1) * $this->config('admin_perpage');
        $limit = intval($this->config('admin_perpage'));
        // Check status
        switch ($searchStatus) {
            case 'open':
                $where['status'] = array(1, 2, 3, 4);
                break;

            case 'finish':
                $where['status'] = array(5);
                break;

            case 'all':
                // $where['status'] = array(1, 2, 3, 4, 5);
                break;
        }
        // Check user
        if ($searchUser > 0) {
            $where['uid'] = intval($searchUser);
        }
        //
        if ($searchLabel > 0) {
            $where['label'] = intval($searchLabel);
        }
        // Get info
        $select = $this->getModel('ticket')->select()->where($where)->order($order)->offset($offset)->limit($limit);
        $rowset = $this->getModel('ticket')->selectWith($select);
        // Make list
        foreach ($rowset as $row) {
            $ticket[$row->id] = Pi::api('ticket', 'support')->canonizeTicket($row);
            $ticket[$row->id]['user'] = Pi::user()->get($row->uid, array('id', 'identity', 'name', 'email'));
            $ticket[$row->id]['ticketUrl'] = Pi::url($this->url('', array(
                'controller' => 'ticket',
                'action' => 'detail',
                'id' => $row->id,
            )));
        }
        // Set paginator
        $count = array('count' => new Expression('count(*)'));
        $select = $this->getModel('ticket')->select()->columns($count)->where($where);
        $count = $this->getModel('ticket')->selectWith($select)->current()->count;
        $paginator = Paginator::factory(intval($count));
        $paginator->setItemCountPerPage($this->config('admin_perpage'));
        $paginator->setCurrentPageNumber($page);
        $paginator->setUrlOptions(array(
            'router'    => $this->getEvent()->getRouter(),
            'route'     => $this->getEvent()->getRouteMatch()->getMatchedRouteName(),
            'params'    => array_filter(array(
                'module'        => $this->getModule(),
                'controller'    => 'index',
                'action'        => 'index',
                'searchStatus' => $searchStatus,
                'searchUser' => $searchUser,
                'searchLabel' => $searchLabel,
            )),
        ));
        // Set form
        $values = array(
            'searchStatus' => $searchStatus,
            'searchUser' => $searchUser,
            'searchLabel' => $searchLabel,
        );
        $form = new SearchForm('search');
        $form->setAttribute('action', $this->url('', array('action' => 'process')));
        $form->setData($values);
        // Set view
        $this->view()->assign('tickets', $ticket);
        $this->view()->assign('paginator', $paginator);
        $this->view()->assign('form', $form);
    }

    public function processAction()
    {
        if ($this->request->isPost()) {
            $data = $this->request->getPost();
            $form = new SearchForm('search');
            $form->setInputFilter(new SearchFilter());
            $form->setData($data);
            if ($form->isValid()) {
                $values = $form->getData();
                $message = __('Go to filter');
                $url = array(
                    'action' => 'index',
                    'searchUser' => $values['searchUser'],
                    'searchStatus' => $values['searchStatus'],
                    'searchLabel' => $values['searchLabel'],
                );
            } else {
                $message = __('Not valid');
                $url = array(
                    'action' => 'index',
                );
            }
        } else {
            $message = __('Not set');
            $url = array(
                'action' => 'index',
            );
        }
        return $this->jump($url, $message);
    }

    public function detailAction()
    {
        // Get info from url
        $module = $this->params('module');
        // Get config
        $config = Pi::service('registry')->config->read($module);
        // Get id
        $id = $this->params('id');
        if ($id) {
            // Get main ticket
            $ticket = Pi::api('ticket', 'support')->getTicket($id);
            $ticket['user'] = Pi::user()->get($ticket['uid'], array('id', 'identity', 'name', 'email'));
            $ticket['user']['avatar'] = Pi::service('user')->avatar($ticket['uid'], 'small', $ticket['user']['name']);
            // Get list of replies
            $tickets = array();
            $where = array('mid' => $id);
            $order = array('time_create ASC', 'id ASC');
            // Get info
            $select = $this->getModel('ticket')->select()->where($where)->order($order);
            $rowset = $this->getModel('ticket')->selectWith($select);
            // Make list
            foreach ($rowset as $row) {
                $tickets[$row->id] = Pi::api('ticket', 'support')->canonizeTicket($row);
                $tickets[$row->id]['user'] = Pi::user()->get($row->uid, array('id', 'identity', 'name', 'email'));
                $tickets[$row->id]['user']['avatar'] = Pi::service('user')->avatar($row->uid, 'small', $tickets[$row->id]['user']['name']);
            }
            // Set view
            $this->view()->assign('ticket', $ticket);
            $this->view()->assign('tickets', $tickets);
            // Set info
            $title = $ticket['subject'];
            $mid = $ticket['id'];
            $status = 0;
        } else {
            // Jump
            $message = __('Please select ticket to see details');
            $url = array('controller' => 'index', 'action' => 'index');
            $this->jump($url, $message);
        }

        // Set form
        $option = array(
            'side' => 'admin',
            'attach' => $config['file_active'],
        );
        $form = new TicketForm('ticket', $option);
        $form->setAttribute('enctype', 'multipart/form-data');
        if ($this->request->isPost()) {
            $data = $this->request->getPost();
            $file = $this->request->getFiles();
            $form->setInputFilter(new TicketFilter($option));
            $form->setData($data);
            if ($form->isValid()) {
                $values = $form->getData();
                // upload file
                if (!empty($file['attach']['name']) && $config['file_active']) {
                    // Set upload path
                    $values['file_path'] = sprintf('%s/%s', date('Y'), date('m'));
                    $path = Pi::path(sprintf('upload/support/%s', $values['file_path']));
                    // Upload
                    $uploader = new Upload;
                    $uploader->setDestination($path);
                    $uploader->setRename('%random%');
                    $uploader->setExtension($this->config('file_extension'));
                    $uploader->setSize($this->config('file_size'));
                    if ($uploader->isValid()) {
                        $uploader->receive();
                        $values['file_title'] = $file['attach']['name'];
                        $values['file_type'] = Pi::api('file', 'support')->getType($file['attach']['name']);
                        $values['file_name'] = $uploader->getUploaded('attach');
                    }
                }
                // Set values
                $values['uid'] = Pi::user()->getId();
                $values['time_create'] = time();
                $values['time_update'] = time();
                $values['ip'] = Pi::user()->getIp();
                $values['mid'] = $mid;
                $values['status'] = $status;
                // Save
                $row = $this->getModel('ticket')->createRow();
                $row->assign($values);
                $row->save();
                // Update main ticket status
                Pi::model('ticket', $this->getModule())->update(
                    array(
                        'status' => 2,
                        'time_update' => time(),
                    ),
                    array('id' => $ticket['id'])
                );
                // Admin ticket
                $ticketAdmin = Pi::api('ticket', 'support')->canonizeTicket($row);
                // Send notification
                Pi::api('notification', 'support')->supportTicket($ticket, 'reply', $ticketAdmin['message']);
                // Update user info
                Pi::api('user', 'support')->updateUser($row->uid, 'reply');
                // Jump
                $message = __('Your answer user support ticket successfully');
                $url = array('controller' => 'index', 'action' => 'index');
                $this->jump($url, $message);
            }
        } elseif (isset($ticket) && !empty($ticket)) {
            $values = array(
                'subject' => sprintf('Re : %s', $ticket['subject']),
            );
            $form->setData($values);
        }
        // Set view
        $this->view()->assign('form', $form);
        $this->view()->assign('title', $title);
        $this->view()->assign('config', $config);
    }

    public function updateAction()
    {
        // Get info from url
        $module = $this->params('module');
        // Get config
        $config = Pi::service('registry')->config->read($module);
        // Set form
        $option = array(
            'selectUser' => 1,
            'side' => 'admin',
            'attach' => $config['file_active'],
        );
        $form = new TicketForm('ticket', $option);
        $form->setAttribute('enctype', 'multipart/form-data');
        if ($this->request->isPost()) {
            $data = $this->request->getPost();
            $file = $this->request->getFiles();
            $form->setInputFilter(new TicketFilter($option));
            $form->setData($data);
            if ($form->isValid()) {
                $values = $form->getData();
                // Check user
                if (isset($values['user']) && $values['user'] > 0) {
                    $message =  $values['message'];
                    // Set values for main ticket
                    $values['uid'] = $values['user'];
                    $values['time_create'] = time();
                    $values['time_update'] = time();
                    $values['ip'] = Pi::user()->getIp();
                    $values['mid'] = 0;
                    $values['status'] = 1;
                    $values['message'] = __('Admin open this ticket for you');
                    // Save main ticket
                    $ticket = $this->getModel('ticket')->createRow();
                    $ticket->assign($values);
                    $ticket->save();
                    // Update user info
                    Pi::api('user', 'support')->updateUser($ticket->uid, 'ticket');
                    // Update count
                    if ($ticket->label > 0) {
                        Pi::api('label', 'support')->updateCount($ticket->label);
                    }
                    // upload file
                    if (!empty($file['attach']['name']) && $config['file_active']) {
                        // Set upload path
                        $values['file_path'] = sprintf('%s/%s', date('Y'), date('m'));
                        $path = Pi::path(sprintf('upload/support/%s', $values['file_path']));
                        // Upload
                        $uploader = new Upload;
                        $uploader->setDestination($path);
                        $uploader->setRename('%random%');
                        $uploader->setExtension($this->config('file_extension'));
                        $uploader->setSize($this->config('file_size'));
                        if ($uploader->isValid()) {
                            $uploader->receive();
                            $values['file_title'] = $file['attach']['name'];
                            $values['file_type'] = Pi::api('file', 'support')->getType($file['attach']['name']);
                            $values['file_name'] = $uploader->getUploaded('attach');
                        }
                    }
                    // Set values for admin ticket
                    $values['uid'] = Pi::user()->getId();
                    $values['time_create'] = time();
                    $values['time_update'] = time();
                    $values['ip'] = Pi::user()->getIp();
                    $values['mid'] = $ticket->id;
                    $values['status'] = 1;
                    $values['message'] = $message;
                    // Save admin ticket
                    $row = $this->getModel('ticket')->createRow();
                    $row->assign($values);
                    $row->save();
                    // Update user info
                    Pi::api('user', 'support')->updateUser($row->uid, 'reply');
                    // Get main ticket
                    $ticketAdmin = Pi::api('ticket', 'support')->canonizeTicket($row);
                    $ticket = Pi::api('ticket', 'support')->canonizeTicket($ticket);
                    $ticket['user'] = Pi::user()->get($ticket['uid'], array('id', 'identity', 'name', 'email'));
                    // Send notification
                    Pi::api('notification', 'support')->supportTicket($ticket, 'admin', $ticketAdmin['message']);
                    // Jump
                    $message = __('Your answer user support ticket successfully');
                    $url = array('controller' => 'index', 'action' => 'index');
                    $this->jump($url, $message);
                }
            }
        }
        // Set view
        $this->view()->assign('form', $form);
        $this->view()->assign('title', __('Open new support ticket'));
        $this->view()->assign('config', $config);
    }

    public function updateStatusAction()
    {
        // Get id
        $id = $this->params('id');
        $return = array();
        // Get order
        $ticket = $this->getModel('ticket')->find($id);
        // Set form
        $form = new StatusForm('status');
        if ($this->request->isPost()) {
            $data = $this->request->getPost();
            $form->setInputFilter(new StatusFilter);
            $form->setData($data);
            if ($form->isValid()) {
                $values = $form->getData();
                $ticket->status = $values['status'];
                $ticket->label = $values['label'];
                $ticket->status_financial = $values['status_financial'];
                $ticket->time_suggested = $values['time_suggested'];
                $ticket->time_execution = $values['time_execution'];
                $ticket->time_update = time();
                $ticket->save();
                // Set return
                $return['status'] = 1;
                $return['data'] = Pi::api('ticket', 'support')->canonizeTicket($ticket);
                if ($values['label'] > 0) {
                    //$label = Pi::api('ticket', 'support')->label($ticket->label);
                    //$return['data'] = array_merge($return['data'], $label);
                    // Update count
                    Pi::api('label', 'support')->updateCount($ticket->label);
                }
            } else {
                $return['status'] = 0;
                $return['data'] = '';
            }
            return $return;
        } else {
            $values['status'] = $ticket->status;
            $values['label'] = $ticket->label;
            $values['status_financial'] = $ticket->status_financial;
            $values['time_suggested'] = $ticket->time_suggested;
            $values['time_execution'] = $ticket->time_execution;
            $form->setData($values);
            $form->setAttribute('action', $this->url('', array(
                'action' => 'updateStatus',
                'id' => $ticket->id
            )));
        }
        // Set view
        $this->view()->setTemplate('system:component/form-popup');
        $this->view()->assign('title', __('Update status'));
        $this->view()->assign('form', $form);
    }

    public function downloadAction()
    {
        $id = $this->params('id');
        if ($id) {
            $ticket = Pi::api('ticket', 'support')->getTicket($id);
            if (!empty($ticket['file_path']) && !empty($ticket['file_name'])) {
                $source = Pi::path(sprintf(
                    'upload/support/%s/%s'                    ,
                    $ticket['file_path'],
                    $ticket['file_name']
                ));
                $options = array(
                    'filename' => $ticket['file_title']
                );
                return Pi::service('file')->download($source, $options);
            } else {
                $this->getResponse()->setStatusCode(404);
                $this->terminate(__('The media not set.'), '', 'error-404');
                $this->view()->setLayout('layout-simple');
                return;
            }
        } else {
            $this->getResponse()->setStatusCode(404);
            $this->terminate(__('The media not set.'), '', 'error-404');
            $this->view()->setLayout('layout-simple');
            return;
        }
    }
}