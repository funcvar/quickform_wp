<?php
/**
 * @Copyright ((c) plasma-web.ru
 * @license    GPLv2 or later
 */

namespace QuickForm;

\defined ('QF3_VERSION') or die;

class formModel extends baseModel {
  public function __construct () {
    $this->closelink = 'forms&projectid=' . (int) $this->get ('projectid', $_POST);
    $this->savelink = 'forms&task=form.edit';
  }

  public function getItems () {
    global $wpdb;
    return $wpdb->get_row ("SELECT * FROM `" . $wpdb->prefix . "qf3_forms` WHERE id = " . (int) $this->get ('id', $_GET));
  }

  public function save () {
    global $wpdb;

    $data = filter_input (INPUT_POST, 'qffield', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
    $id = (int) $this->get ('id', $_GET, 0);

    if (! $data['title']) {
      $this->errors[] = 'The title is not filled in.';
      return $id;
    }

    if (qf::gettask () == 'form.savecopy') {
      $id = 0;
      $data['title'] = '- ' . $data['title'];
    }


    $projectid = (int) $this->get ('projectid', $_POST);
    if (! $projectid) {
      $this->errors[] = 'no project id';
      return;
    }
    $def = $id ? (int) $this->get ('def', $_POST) : $this->setDef ($projectid);

    $json = filter_input (INPUT_POST, 'fields', FILTER_DEFAULT);
    $ob = json_decode ($json);
    if ($ob === null) {
      $this->errors[] = 'json cannot be decoded.';
      return $id;
    }

    $inputData = array(
      'title' => strip_tags ($data['title']),
      'id' => (int) $id,
      'def' => (int) $def,
      'projectid' => (int) $projectid,
      'fields' => $json,
    );

    if ($id) {
      $wpdb->update ($wpdb->prefix . 'qf3_forms', $inputData, array('id' => $id));
      return $id;
    }
    else {
      $wpdb->insert ($wpdb->prefix . 'qf3_forms', $inputData);
      return $wpdb->insert_id;
    }
  }

  public function ajax () {
    $id = (int) $this->get ('id', $_POST);

    switch ($this->get ('mod', $_POST)) {
      case 'text': {
          echo Text::_ (strip_tags ($this->get ('str', $_POST)));
        }
        break;

      case 'selectors': {
          echo $this->getSelectors ($id);
        }
        break;

      case 'getForms': {
          echo $this->getForms ($id);
        }
        break;

      case 'fieldGroupTitle': {
          echo $this->fieldGroupTitle ($id);
        }
        break;
    }

  }

  protected function fieldGroupTitle ($id) {
    global $wpdb;

    if ($id) {
      $title = $wpdb->get_var ('SELECT title FROM ' . $wpdb->prefix . 'qf3_forms WHERE id = ' . (int) $id);
      return Text::_ ($title);
    }
    return '';
  }

  protected function getForms ($id) {
    global $wpdb;

    $forms = $wpdb->get_results ('SELECT id, title FROM ' . $wpdb->prefix . 'qf3_forms WHERE projectid = ' . (int) $id);
    $sections[] = '<option value="">' . Text::_ ('QF_NOT_SELECTED') . '</option>';
    foreach ($forms as $form) {
      $sections[] = '<option value="' . $form->id . '">' . Text::_ ($form->title) . '</option>';
    }

    return '<select id="filter_form" name="filter_form">' . implode ('', $sections) . '</select>';
  }

  protected function getSelectors ($id) {
    global $wpdb;

    $html = '<div class="qfselectors">';

    if ($id) {
      $projectid = $wpdb->get_var ('SELECT projectid FROM ' . $wpdb->prefix . 'qf3_forms WHERE id = ' . (int) $id);
    }
    else {
      $projectid = (int) $this->get ('projectid', $_POST);
    }


    $html .= '<div>' . Text::_ ('QF_PROGECTS') . ': ';
    $sections = array();
    $projects = $wpdb->get_results ('SELECT id, title FROM ' . $wpdb->prefix . 'qf3_projects');
    foreach ($projects as $project) {
      $selselected = (int) $project->id == (int) $projectid ? ' selected="selected"' : '';
      $sections[] = '<option value="' . $project->id . '"' . $selselected . '>' . Text::_ ($project->title) . '</option>';
    }

    $html .= '<select id="filter_project" name="filter_project">' . implode ('', $sections) . '</select>';
    $html .= '</div>';
    $html .= '<div>' . Text::_ ('QF_FIELD_GROUPS') . ': ';
    $sections = array();
    $forms = $wpdb->get_results ('SELECT id, title FROM ' . $wpdb->prefix . 'qf3_forms WHERE projectid = ' . (int) $projectid);

    $sections[] = '<option value="">' . Text::_ ('QF_NOT_SELECTED') . '</option>';

    foreach ($forms as $form) {
      $selselected = (int) $form->id == (int) $id ? ' selected="selected"' : '';
      $sections[] = '<option value="' . $form->id . '"' . $selselected . '>' . Text::_ ($form->title) . '</option>';
    }

    $html .= '<select id="filter_form" name="filter_form">' . implode ('', $sections) . '</select>';
    $html .= '</div>';

    $html .= '</div>';
    return $html;
  }

  protected function setDef ($projectid) {
    global $wpdb;
    $def = $wpdb->get_var ('SELECT id FROM ' . $wpdb->prefix . 'qf3_forms WHERE def = 1 AND projectid = ' . (int) $projectid);
    return ($def ? 0 : 1);
  }

}