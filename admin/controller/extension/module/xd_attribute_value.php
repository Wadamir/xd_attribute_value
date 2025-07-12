<?php
class ControllerExtensionModuleXDAttributeValue extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('extension/module/xd_attribute_value');
        $this->document->setTitle($this->language->get('heading_name'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_xd_attribute_value', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/xd_attribute_value', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/xd_attribute_value', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        if (isset($this->request->post['module_xd_attribute_value_status'])) {
            $data['module_xd_attribute_value_status'] = $this->request->post['module_xd_attribute_value_status'];
        } else {
            $data['module_xd_attribute_value_status'] = $this->config->get('module_xd_attribute_value_status');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/xd_attribute_value', $data));
    }

    public function install()
    {
        $sql = "SHOW TABLES LIKE '" . DB_PREFIX . "attribute_value'";
        if (count($this->db->query($sql)->rows) == 0) { // if not installed
            $sql = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "attribute_value` (
                `attribute_value_id` INT(11) NOT NULL AUTO_INCREMENT,
                `attribute_id` INT(11) NOT NULL,
                `sort_order` INT(3) NOT NULL DEFAULT '0',
                PRIMARY KEY (`attribute_value_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
            $this->db->query($sql);
        }

        $sql = "SHOW TABLES LIKE '" . DB_PREFIX . "attribute_value_description'";
        if (count($this->db->query($sql)->rows) == 0) { // if not installed
            $sql = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "attribute_value_description` (
                `attribute_value_id` INT(11) NOT NULL,
                `language_id` INT(11) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`attribute_value_id`, `language_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
            $this->db->query($sql);
        }
    }

    public function uninstall()
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "attribute_value`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "attribute_value_description`");
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/module/xd_attribute_value')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }
}
