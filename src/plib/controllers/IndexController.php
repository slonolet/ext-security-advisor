<?php

class IndexController extends pm_Controller_Action
{
    public function init()
    {
        parent::init();

        $this->view->pageTitle = $this->lmsg('pageTitle');
        $this->view->tabs = [
            [
                'title' => $this->lmsg('tabs.domains'),
                'action' => 'domain-list',
            ],
            [
                'title' => $this->lmsg('tabs.wordpress'),
                'action' => 'wordpress-list',
            ],
            [
                'title' => $this->lmsg('tabs.settings'),
                'action' => 'settings',
            ],
        ];
    }

    public function indexAction()
    {
        $this->_forward('domain-list');
    }

    public function domainListAction()
    {
        $this->view->list = $this->_getDomainsList();
    }

    public function domainListDataAction()
    {
        $this->_helper->json($this->_getDomainsList()->fetchData());
    }

    private function _getDomainsList()
    {
        $list = new Modules_SecurityWizard_View_List_Domains($this->view, $this->_request);
        $list->setDataUrl(['action' => 'domain-list-data']);
        return $list;
    }

    public function letsencryptAction()
    {
        if (!$this->_request->isPost()) {
            throw new pm_Exception('Post request is required');
        }
        $successDomains = [];
        $messages = [];
        foreach ((array)$this->_getParam('ids') as $domainId) {
            try {
                $domain = new pm_Domain($domainId);
                Modules_SecurityWizard_Letsencrypt::run($domain->getName());
                $successDomains[] = $domain->getName();
            } catch (pm_Exception $e) {
                $messages[] = ['status' => 'error', 'content' => $this->view->escape($e->getMessage())];
            }
        }

        if ($successDomains) {
            $domainLinks = implode(', ', array_map(function ($domainName) {
                return "<a href='https://{$domainName}' target='_blank'>{$domainName}</a>";
            }, $successDomains));
            $successMessage = $this->lmsg('controllers.letsencrypt.successMsg', ['domains' => $domainLinks]);
            $messages[] = ['status' => 'info', 'content' => $successMessage];
            $status = 'success';
        } else {
            $status = 'error';
        }
        $this->_helper->json(['status' => $status, 'statusMessages' => $messages]);
    }

    public function installLetsencryptAction()
    {
        if (!$this->_request->isPost()) {
            throw new pm_Exception('Post request is required');
        }
        Modules_SecurityWizard_Extension::install(Modules_SecurityWizard_Letsencrypt::INSTALL_URL);
        $this->_redirect('index/domain-list');
    }

    public function wordpressListAction()
    {
        $this->view->list = $this->_getWordpressList();
    }

    public function wordpressListDataAction()
    {
        $this->_helper->json($this->_getWordpressList()->fetchData());
    }

    private function _getWordpressList()
    {
        $list = new Modules_SecurityWizard_View_List_Wordpress($this->view, $this->_request);
        $list->setDataUrl(['action' => 'wordpress-list-data']);
        return $list;
    }

    public function settingsAction()
    {
        $returnUrl = pm_Context::getActionUrl('index', 'settings');

        $form = new Modules_SecurityWizard_View_Form_Settings([
            'returnUrl' => $returnUrl
        ]);

        if ($this->_request->isPost() && $form->isValid($this->_request->getPost())) {
            try {
                $form->process();
            } catch (pm_Exception $e) {
                $this->_status->addError($e->getMessage());
                $this->_helper->json(['redirect' => $returnUrl]);
            }
            $this->_status->addInfo($this->lmsg('controllers.settings.save.successMsg'));
            $this->_helper->json(['redirect' => $returnUrl]);
        }

        $this->view->form = $form;
    }
}
