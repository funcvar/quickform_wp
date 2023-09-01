<?php
/**
* @Copyright ((c) plasma-web.ru
        * @license    GPLv2 or later
        */

namespace QuickForm;

\defined('QF3_VERSION') or die;

class historyModel extends baseModel
{
    public function __construct()
    {
        $this->closelink = 'historys';
        $this->savelink = 'historys&task=history.edit';
    }

    public function getItems()
    {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM `" . $wpdb->prefix . "qf3_ps` WHERE id = " .(int) $_GET['id']);
    }

    public function save()
    {
        global $wpdb;

        $data = filter_input(INPUT_POST, 'qffield', FILTER_DEFAULT , FILTER_REQUIRE_ARRAY);
        $id = (int) $this->get('id', $_GET, 0);

        if(! $data['st_title']) {
            $this->errors[] = 'The title is not filled in.';
            return $id;
        }

        $inputData = array(
            'st_title' 	=> substr(htmlspecialchars(strip_tags($data['st_title']), ENT_QUOTES),0, 250),
            'id' 	=> (int) $id,
            'st_status' 	=> (int) $this->get( 'st_status', $data ),
            'st_desk'   => $this->get( 'st_desk', $data ),
         );

        if($id) {
            $wpdb->update( $wpdb->prefix.'qf3_ps', $inputData, array( 'id' => (int) $inputData['id'] ));
            return $inputData['id'];
        }
    }

}
