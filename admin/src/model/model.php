<?php
/**
* @Copyright ((c) plasma-web.ru
        * @license    GPLv2 or later
        */

namespace QuickForm;

\defined('QF3_VERSION') or die;

class baseModel extends qf_admin
{
    public $closelink;
    public $savelink;
    public $savecreatelink;

    public function filterdir() {
        $col = qf::gettask('col');
        $ses = qf::ses()->get('quickform', []);
        $filter = $this->get('filterdir', $ses, []);
        $order = $this->get('order', $filter, 'id');

        if($col == $order) {
            $dir = $this->get('dir', $filter) === 'asc' ? 'desc' : 'asc';
            $ses['filterdir']['dir'] = $dir;
            qf::ses()->set('quickform', $ses);
        }
        else {
          $ses['filterdir']['order'] =  $col;
          qf::ses()->set('quickform', $ses);
        }

    }

    public function filterlist($page)
    {
        $col = filter_input(INPUT_POST, 'col', FILTER_SANITIZE_STRING);
        $v = filter_input(INPUT_POST, 'v', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ($col) {
          $ses = qf::ses()->get('quickform', []);
          $ses['filterlist'][$page.'.'.$col] = $v;
          qf::ses()->set('quickform', $ses);
        }
    }

    public function publish($page)
    {
        global $wpdb;

        if($page == 'projects') {
            $wpdb->query( 'UPDATE '.$wpdb->prefix.'qf3_projects SET published = 1 - published WHERE id=' .(int) $_GET['id'] );
        }
    }

    public function activate($page)
    {
        global $wpdb;

        $new_id = $this->checkcid();
        if($new_id && $page == 'projects') {
            $res = $wpdb->query( 'UPDATE '.$wpdb->prefix.'qf3_projects SET published = 1 WHERE id IN ('.implode(',', $new_id).')' );
            if($res) $this->messages[] = $res.' '.'QF_N_ITEMS_PUBLISHED';
            elseif($res === false) $this->errors[] = 'QF_ERR_DATABASE';
        }
    }

    public function deactivate($page)
    {
        global $wpdb;

        $new_id = $this->checkcid();
        if($new_id && $page == 'projects') {
            $res = $wpdb->query( 'UPDATE '.$wpdb->prefix.'qf3_projects SET published = 0 WHERE id IN ('.implode(',', $new_id).')' );
            if($res) $this->messages[] = $res.' '.'QF_N_ITEMS_UNPUBLISHED';
            elseif($res === false) $this->errors[] = 'QF_ERR_DATABASE';
        }
    }

    public function delete($page)
    {
        global $wpdb;

        $new_id = $this->checkcid();
        if($new_id) {
            $res = $wpdb->query( 'DELETE FROM '.$wpdb->prefix.'qf3_'.$page.' WHERE id IN ('.implode(',', $new_id).')' );
            if($res) $this->messages[] = $res.' '.'QF_N_ITEMS_DELETED';
            elseif($res === false) $this->errors[] = 'QF_ERR_DATABASE';
        }
    }

    protected function checkcid()
    {
        return array_diff(filter_input(INPUT_POST, 'cid', FILTER_SANITIZE_NUMBER_INT , FILTER_REQUIRE_ARRAY), array(''));
    }

    public function help()
    {
        echo Text::_('QF_HELP');
    }

}
