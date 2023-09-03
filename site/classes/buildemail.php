<?php
/**
 * @Copyright ((c) plasma-web.ru
 * @license    GPLv2 or later
 */

namespace QuickForm;

\defined ('QF3_VERSION') or die;

use PHPMailer\PHPMailer\PHPMailer;

require_once(__DIR__ . "/calculator.php");
require_once(__DIR__ . "/email.php");

class qfFilds {
  public $submited = false;
  public $calculated = false;
  public $iscart = false;
  public $errormes = array();
  public $project = false;
  public $redirect = false;
  public $email = false;
  protected $child = array();
  public $back = false;
  protected $stepperdata = array();
  protected $fileListToEmail = array();
  public $fileListToServer = array();
  public $data = array();
  // protected $db = false;
  protected $modify = false;

  public function __construct () {

  }

  public function submitForm () {
    $this->qfcheckToken ();

    $id = (int) qf::get ('id', $_POST);
    $msg = '';

    $project = $this->getProjectById ($id);
    if (empty($project)) {
      $msg = Text::_ ('QF_CANNOT_BE_SENT');
    }

    if (! $msg) {
      $html = $this->getResultHtml ($project);
    }

    if (! $msg && ! $this->submited) {
      $msg = Text::_ ('QF_CANNOT_BE_SENT');
    }

    if (! $msg && $this->getErrormes ()) {
      $msg = implode ('<br>', $this->getErrormes ());
    }

    if (! $msg && $this->fileListToServer) {
      $html = $this->uploadfiles ($html);
      if ($err = $this->getErrormes ()) {
        $msg = implode ('<br>', $err);
      }
    }

    if (! $msg) {
      $stat = $this->writeStat ($project, $html);
      if (! $stat) {
        $msg = Text::_ ('QF_NOT_COMPLETED');
      }
    }

    if (! $msg) {
      if (! $this->sendMail ($project, $html, $stat)) {
        $msg = Text::_ ('QF_NOT_COMPLETED');
      }
    }

    $msgtype = 'error';
    if (! $msg) {
      $msg = $this->replacevars (Text::translate (qf::get ('thnq_message', $project->params)), $stat);
      $msgtype = 'message';

      $ses = qf::ses ()->get ('quickform', []);
      $ses['adder'] = [];
      qf::ses ()->set ('quickform', $ses);
    }

    return array($msg, $msgtype);
  }

  public function getResultHtml ($project) {
    $data = $this->getData ($project->id);
    $project->calculated = $this->calculated && qf::get ('calculatortype', $project->params);
    $calculator = qfCalculator::getCalculator ($project, $data);
    $html = qfEmail::getEmailHtml ($project, $data, $calculator);
    return Text::translate ($html);
  }

  public function sumCustomAjax () {
    $strarr = array();
    $id = (int) qf::get ('id', $_POST);
    $project = $this->getProjectById ($id);
    if (! $project) {
      return '';
    }
    $data = $this->getData ($project->id);
    $project->calculated = $this->calculated && $project->params->calculatortype;
    $sumarr = qfCalculator::getCalculator ($project, $data);
    foreach ($sumarr as $arr) {
      $strarr[] = $arr[1]->fieldid . ':' . $arr[0];
    }
    return implode (';', $strarr);
  }

  public function getErrormes () {
    $err = qfCalculator::qfErrormes ();
    return array_merge ($this->errormes, $err);
  }

  public function qfcheckToken () {
    if (explode ('/', str_replace (array('w', '.', '-', '|'), '', qf::get ('root', $_POST)))[2] != qf::get ('qftoken', $_POST, 's')) {
      exit('INVALID TOKEN');
    }
  }

  public function getProjectById ($id) {
    global $wpdb;
    if ($this->project) {
      return $this->project;
    }

    $this->project = $wpdb->get_row ('SELECT * FROM ' . $wpdb->prefix . 'qf3_projects WHERE published=1 AND (language="' . \esc_sql (qf::getlang ()) . '" OR language= "") AND (access IN ("' . implode ('", "', \esc_sql (qf::user ()->get ('roles'))) . '") OR access="" OR access="0") AND id = ' . (int) $id);

    if (empty($this->project)) {
      return false;
    }

    $this->project->params = json_decode ($this->project->params);
    return $this->project;
  }

  public function getData ($projectid) {
    global $wpdb;

    $form = $wpdb->get_row ('SELECT * FROM ' . $wpdb->prefix . 'qf3_forms WHERE def=1 AND projectid = ' . (int) $projectid);
    if (! empty($form)) {
      $this->data = $this->getFields ($form);
    }
    return $this->data;
  }

  protected function getChildren ($id) {
    global $wpdb;
    if (! isset($this->child[(int) $id])) {
      $this->child[(int) $id] = $wpdb->get_row ('SELECT * FROM ' . $wpdb->prefix . 'qf3_forms WHERE id = ' . (int) $id);
    }

    return $this->getFields ($this->child[(int) $id]);
  }

  protected function getFields ($form) {
    if (! $form) {
      return array();
    }
    $data = array();
    $fields = json_decode ($form->fields);

    foreach ($fields as $field) {
      $field->fieldid = $form->id . '.' . $field->fildnum;
      unset($field->fildnum);

      if (! isset($field->hide)) {
        $field->hide = 0;
      }

      switch ($field->teg) {
        case 'select':
          $data[] = $this->select ($field);
          break;
        case 'input[radio]':
          $data[] = $this->radio ($field);
          break;
        case 'input[checkbox]':
        case 'qf_checkbox':
          $data[] = $this->checkbox ($field);
          break;
        case 'textarea':
          $data[] = $this->textarea ($field);
          break;
        case 'customHtml':
          $data[] = $this->customHtml ($field);
          break;
        case 'customPhp':
          $data[] = $this->customPhp ($field);
          break;
        case 'calculatorSum':
          $data[] = $this->calculatorSum ($field);
          break;
        case 'recaptcha':
          $this->recaptcha ($field);
          break;
        case 'submit':
          $this->submited = true;
          $this->redirect = trim (qf::get ('redirect', $field));
          $this->email = trim (qf::get ('email', $field));
          break;
        case 'backemail':
          $data[] = $this->backemail ($field);
          break;
        case 'cloner':
          $data[] = $this->cloner ($field);
          break;
        case 'qfcalendar':
          $data[] = $this->qfcalendar ($field);
          break;
        case 'stepperbox':
          $data[] = $this->stepperbox ($field);
          break;
        case 'stepperbtns':
          $data[] = $this->stepperbtns ($field);
          break;
        case 'spoiler':
        case 'qfincluder':
          $data[] = $this->qfincluder ($field);
          break;
        case 'addercart':
          $data[] = $this->addercart ($field);
          break;
        case 'qftabs':
          $data[] = $this->qftabs ($field);
          break;
        case 'addToCart':
          $this->iscart = true;
          break;
        case 'input[file]':
        case 'qf_file':
          $data[] = $this->qffile ($field);
          break;
        case 'input[button]':
        case 'input[reset]':
        case 'boxadder':
          break;
        default:
          $data[] = $this->getDefault ($field);
      }
    }

    return $data;
  }


  protected function chekRequired ($field) {
    if (qf::get ('required', $field)) {
      if (! $field->value) {
        if (! $err = Text::translate ($this->findLable ($field))) {
          $err = $field->teg;
        }

        $this->errormes[] = Text::_ ('QF_NOT_ALL') . ': ' . $err;
      }
    }
  }

  protected function checklist ($name, $i, $filter = false) {
    if (isset($_POST[$name][$i])) {
      if ($name == 'qftextarea') {
        $value = strip_tags ($_POST[$name][$i], '<a></a>');
      }
      else
        $value = strip_tags ($_POST[$name][$i]);
    }
    else {
      $this->errormes[] = 'Form error: ' . $name;
      return;
    }

    if ($filter) {
      $value = filter_var ($value, $filter);
    }

    return $value;
  }

  protected function findlatest ($name, $filter = FILTER_DEFAULT) {
    $res = (array) filter_input (INPUT_POST, $name, $filter, FILTER_REQUIRE_ARRAY);
    return end ($res);
  }

  protected function getDefault ($field) {
    static $i = array();

    $name = 'qf' . str_replace (array('input[', ']', 'qf_'), '', $field->teg);

    $i[$name] = isset($i[$name]) ? $i[$name] : 0;
    $field->value = htmlspecialchars ($this->checklist ($name, $i[$name]), ENT_NOQUOTES);

    $this->chekRequired ($field);
    $i[$name]++;

    return $field;
  }

  protected function select ($field) {
    static $i = 0;

    $field->value = (int) $this->checklist ('qfselect', $i);
    $this->chekRequired ($field);

    $option = $field->options[$field->value];
    $field->math = qf::get ('math', $option);
    $field->option['value'] = $field->value;
    $field->value = $option->label;
    unset($field->options);
    $i++;

    if ($id = (int) qf::get ('related', $option)) {
      $field->data = $this->getChildren ($id);
    }

    return $field;
  }

  protected function radio ($field) {
    static $i = 0;

    if ($this->checklist ('qfradio', $i) === '') {
      $this->chekRequired ($field);
      $field->value = '';
      $field->hide = 1;
      unset($field->options);
      $i++;
      return $field;
    }

    $field->value = (int) $this->checklist ('qfradio', $i);

    $option = $field->options[$field->value];
    $value = $this->imgEmail ($option) . $option->label;

    $field->math = qf::get ('math', $option);
    $field->option['value'] = $field->value;
    $field->value = $value;
    unset($field->options);
    $i++;

    if ($id = (int) qf::get ('related', $option)) {
      $field->data = $this->getChildren ($id);
    }

    return $field;
  }

  protected function checkbox ($field) {
    static $i = 0;

    $field->value = (int) $this->checklist ('qfcheckbox', $i);
    $this->chekRequired ($field);

    if (! $field->value) {
      $field->math = '';
    }

    $i++;

    if ($field->value) {
      if ($id = (int) qf::get ('related', $field)) {
        $field->data = $this->getChildren ($id);
      }
      $field->value = 'QF_YES';
    }
    else {
      if ($field->hide == 2) {
        $field->hide = 1;
      }
      $field->value = 'QF_NO';
    }

    return $field;
  }

  protected function textarea ($field) {
    static $i = 0;

    $value = preg_replace ('/<a.*?href=["\'](.*?)["\'].*?>(.*?)<\/a>/i', '$2 ( $1 )', $this->checklist ('qftextarea', $i));
    $field->value = htmlspecialchars ($value, ENT_NOQUOTES);

    if (qf::get ('tmpl', $this->project->params) != 'simple') {
      $field->value = nl2br ($field->value);
    }

    $this->chekRequired ($field);
    $i++;

    return $field;
  }

  protected function customHtml ($field) {
    if (! qf::get ('qfshowl', $field)) {
      $field->hide = 1;
    }

    return $field;
  }

  protected function customPhp ($field) {
    $field->value = '';
    if (! qf::get ('customphp2', $field)) {
      return $field;
    }

    $tmpfname = tempnam (sys_get_temp_dir (), "qf");
    $handle = fopen ($tmpfname, "w");
    fwrite ($handle, $field->customphp2, strlen ($field->customphp2));
    fclose ($handle);
    if (is_file ($tmpfname)) {
      ob_start ();
      include $tmpfname;
      $field->value = ob_get_clean ();
    }
    unlink ($tmpfname);
    return $field;
  }

  protected function calculatorSum ($field) {
    $this->calculated = true;
    $field->hide = 1;
    $field->unit = qf::get ('unit', $field);
    $field->pos = qf::get ('pos', $field);
    $field->fixed = qf::get ('fixed', $field, 0);
    $field->format = qf::get ('format', $field, 0);
    return $field;
  }

  protected function recaptcha ($field) {
    if (! in_array (qf::get ('task', $_POST), array('qfsubmit', 'ajax.qfajax'))) {
      return;
    }

    if (qf::user ()->get ('guest') || ! qf::conf ()->get ('recaptcha_show')) {
      if (! isset($_POST["g-recaptcha-response"])) {
        $this->errormes[] = 'RECAPTCHA ERROR';
        return;
      }
      $url = 'https://www.google.com/recaptcha/api/siteverify';
      $data = [
        'secret' => qf::conf ()->get ('serverkey'),
        'response' => $_POST["g-recaptcha-response"]
      ];
      $options = [
        'http' => [
          'method' => 'POST',
          'content' => http_build_query ($data)
        ]
      ];
      $context = stream_context_create ($options);
      $verify = file_get_contents ($url, false, $context);
      $res = json_decode ($verify);
      if (! $res->success) {
        $this->errormes[] = 'RECAPTCHA ERROR';
      }
    }
  }

  protected function backemail ($field) {
    $useremail = qf::user ()->get ('email');
    if (qf::conf ()->get ('unlogged')) {
      $new = $this->findlatest ('qfuseremail', FILTER_VALIDATE_EMAIL);
      if ($new)
        $useremail = $new;
    }
    $field->hide = 1;

    if ($useremail) {
      if (! qf::get ('backhide', $field)) {
        $field->value = (int) qf::get ('qfbackemail', $_POST);
        $this->chekRequired ($field);
      }
      else
        $field->value = 1;

      if ($field->value)
        $this->back = $useremail;
    }
    elseif (! qf::get ('backhide', $field)) {
      $field->value = '';
      $this->chekRequired ($field);
    }

    return $field;
  }

  protected function cloner ($field) {
    static $i = 0;

    $val = (int) $this->checklist ('qfcloner', $i);

    if (! $val) {
      $this->errormes[] = 'Form error: qfcloner empty';
    }

    $max = (int) qf::get ('max', $field);
    if ($max && $val > $max) {
      $this->errormes[] = 'Form error: qfcloner max';
    }

    $field->value = $val;
    $field->orient = qf::get ('orient', $field);
    $field->data = array();
    $i++;

    for ($n = 0; $n < $val; $n++) {
      $field->data[] = $this->getChildren ($field->related);
    }

    return $field;
  }

  protected function qfcalendar ($field) {
    static $i = 0;

    $math = qf::get ('math', $field);
    $format = qf::get ('format', $field, 'd-m-Y');

    if (qf::get ('double', $field)) {
      $val1 = $this->is_date_valid ($this->checklist ('qfcalendar', $i), $format);
      $i++;
      $val2 = $this->is_date_valid ($this->checklist ('qfcalendar', $i), $format);

      if ($val1 && $val2) {
        $field->value = $val1 . ' — ' . $val2;
        if (strpos ($math, 'v') !== false) {
          $date1 = \DateTime::createFromFormat ($format, $val1);
          $date2 = \DateTime::createFromFormat ($format, $val2);
          $diff = (strtotime ($date2->format ('Y-m-d H:i')) - strtotime ($date1->format ('Y-m-d H:i'))) / 3600 / 24;
          if ($diff < 0) {
            $diff = 0;
          }
          else {
            $diff = ceil ($diff);
          }
          $field->math = str_replace ('v', $diff, $field->math);
        }
      }
      else {
        $field->value = '';
      }

    }
    else {
      $field->value = $this->is_date_valid ($this->checklist ('qfcalendar', $i), $format);
      if (strpos ($math, 'v') !== false) {
        $field->math = str_replace ('v', '0', $field->math);
      }
    }

    $this->chekRequired ($field);
    $i++;

    return $field;
  }

  protected function is_date_valid ($date, $format) {
    if (! $date)
      return '';

    $parsed_date = date_parse_from_format ($format, $date);
    if (! $parsed_date['error_count'] && ! $parsed_date['warning_count']) {
      return $date;
    }

    $this->errormes[] = Text::_ ('Invalid date') . ': ' . htmlspecialchars ($date);
    return '';
  }

  protected function stepperbox ($field) {
    static $i = 0;
    $this->stepperdata[$i] = array();
    $field->hide = 3;
    if ($id = qf::get ('related', $field)) {
      $data = $this->getChildren ($id);
      $this->recursively ($data, $i);
      $field->data = array_merge ($data, $this->stepperdata[$i]);
    }
    $i++;

    return $field;
  }

  protected function stepperbtns ($field) {
    static $i = 0;

    $val = (int) $this->checklist ('qfstepper', $i);
    $i++;
    $field->hide = 3;

    if ($val && $id = qf::get ('related', $field)) {
      $field->step = $id;
    }

    return $field;
  }

  protected function recursively ($data, $i) {
    foreach ($data as $field) {
      if ($field->teg == 'stepperbtns') {
        if (isset($field->step) && ! empty($field->step)) {
          $dat = $this->getChildren ($field->step);
          $this->stepperdata[$i] = array_merge ($this->stepperdata[$i], $dat);
          $this->recursively ($dat, $i);
        }
      }
      else {
        if (isset($field->data) && ! empty($field->data)) {
          $this->recursively ($field->data, $i);
        }
      }
    }
  }

  protected function qfincluder ($field) {
    $field->hide = 3;
    if ($id = qf::get ('related', $field)) {
      $field->data = $this->getChildren ($id);
    }

    return $field;
  }

  protected function addercart ($field) {
    $ses = qf::ses ()->get ('quickform', []);
    $field->rows = qf::get ('adder', $ses, []);

    return $field;
  }

  protected function qftabs ($field) {
    foreach ($field->options as $option) {
      if ($id = (int) qf::get ('related', $option)) {
        $field->data[] = $this->getChildren ($id);
      }
      else {
        $field->data[] = array();
      }
    }

    return $field;
  }

  protected function qffile ($field) {
    static $i = 0;
    // $rfiles = \sanitize_post($_FILES, 'raw');

    if (qf::gettask () == 'ajax.sumCustom')
      return $field;
    $rfiles = $_FILES;

    if (! isset($rfiles['inpfile']['name'][$i])) {
      $this->errormes[] = 'Form error: input[file]';
      $i++;
      return $field;
    }
    if (! isset($field->filetoemail)) {
      $field->filetoemail = 1;
    }
    if (! isset($field->extens)) {
      $field->extens = "jpg,gif,png";
    }

    $field->filelist = array();
    $extens = explode (',', strtolower (str_replace (' ', '', qf::get ('extens', $field))));
    $extens = array_diff ($extens, array(''));
    $html = '';

    foreach ($rfiles['inpfile']['name'][$i] as $k => $v) {
      if ($v) {
        $hv = htmlspecialchars ($v);
        $err = $rfiles['inpfile']['error'][$i][$k];
        if ($err) {
          if ($err == 1) {
            $this->errormes[] = Text::_ ('QF_ERR_DOWNLOAD') . ': ' . $hv;
          }
          else {
            $this->errormes[] = 'error: Failed to load file: ' . $hv;
          }
        }
        if ($rfiles['inpfile']['tmp_name'][$i][$k] == 'none' || ! is_uploaded_file ($rfiles['inpfile']['tmp_name'][$i][$k])) {
          $this->errormes[] = 'error: Failed to load file: ' . $hv;
        }
        if (mb_substr (trim ($v), 0, 1, "UTF-8") == '.') {
          $this->errormes[] = Text::_ ('QF_ERR_FILE_NAME') . ': ' . $hv;
        }

        if (preg_replace ('/[\/:*?"<>|+%!@]/', '', $v) != $v) {
          $this->errormes[] = Text::_ ('QF_ERR_FILE_NAME') . ': ' . $hv;
        }

        if ($extens) {
          if (! in_array (strtolower (pathinfo ($v, PATHINFO_EXTENSION)), $extens)) {
            $this->errormes[] = Text::_ ('QF_ERR_FILE_NAME') . ': ' . $hv;
          }
        }
        $arr = array(
          'name' => $v,
          'tmp_name' => $rfiles['inpfile']['tmp_name'][$i][$k],
          'type' => $rfiles['inpfile']['type'][$i][$k],
          'size' => $rfiles['inpfile']['size'][$i][$k],
          'error' => $rfiles['inpfile']['error'][$i][$k]
        );
        $field->filelist[] = $arr;

        if (qf::get ('filetoemail', $field)) {
          $this->fileListToEmail[] = $arr;
        }

        if (qf::conf ()->get ('filesmod') && qf::get ('filetoserver', $field)) {
          $this->fileListToServer[] = $arr;
          $html .= '<a href="' . QF3_PLUGIN_URL . 'site/assets/attachment/COM_QF_TEMP_FOLDER_NAME/' . $v . '">' . $v . '</a><br/>';
        }
        else {
          $html .= $v . '<br/>';
        }
      }
    }

    $field->value = $html;

    $this->chekRequired ($field);
    $i++;

    return $field;
  }


  public function extBlacklist () {
    return array('.php', '.cgi', '.pl', '.fcgi', '.scgi', '.sql', '.phtml', '.asp', '.js', '.py', '.exe', '.htm', '.htaccess', '.htpasswd', '.ini', '.sh', '.log');
  }

  public function uploadfiles ($html) {
    if (! empty($this->fileListToServer)) {
      $foldername = (int) time ();
      if ($foldername < 1601572894) {
        $this->errormes[] = 'error: Failed to load file';
        return false;
      }

      $path = dirname (__DIR__) . '/assets/attachment/' . $foldername . '/';
      if (! is_dir ($path)) {
        mkdir ($path, 0777, true);
      }

      foreach ($this->fileListToServer as $file) {
        if (str_replace ($this->extBlacklist (), '', strtolower ($file['name'])) != strtolower ($file['name'])) {
          $this->errormes[] = Text::_ ('QF_ERR_FILE_NAME') . ': ' . $file['name'];
          return false;
        }
        if (! copy ($file['tmp_name'], $path . $file['name'])) {
          $this->errormes[] = 'error: Failed to load file: ' . $file['name'];
          return false;
        }
      }

      return str_replace ('COM_QF_TEMP_FOLDER_NAME', $foldername, $html);
    }
    return $html;
  }

  public function linerData ($data, $mathOnly = false) {
    $newdata = array();

    foreach ($data as $field) {
      if ($field->teg == 'cloner' || $field->teg == 'qftabs') {
        foreach ($field->data as $row) {
          $arr = $this->linerData ($row, $mathOnly);
          $newdata = array_merge ($newdata, $arr);
        }
      }
      else {

        if ($mathOnly) {
          if (isset($field->math) && $field->math !== '') {
            $newdata[] = $field;
          }
        }
        else {
          $newdata[] = $field;
        }
        if (isset($field->data) && ! empty($field->data)) {
          $arr = $this->linerData ($field->data, $mathOnly);
          $newdata = array_merge ($newdata, $arr);
        }
      }
    }

    return $newdata;
  }

  public function imgEmail ($fd) {
    if (qf::get ('imginemail', $fd) && $fd->img) {
      return '<img src="' . $fd->img . '" width="' . qf::get ('imgw', $fd, '40') . '" height="' . qf::get ('imgh', $fd, '40') . '">';
    }
    return '';
  }

  public function getSimplRows ($data) {
    $html = '';
    foreach ($data as $field) {
      if (! qf::get ('value', $field)) {
        if ($field->hide == 2) {
          $field->hide = 1;
        }
      }

      if ($field->hide != 1) {
        if ($field->teg == 'cloner') {
          foreach ($field->data as $row) {
            $html .= $this->getSimplRows ($row);
          }
        }
        elseif ($field->teg == 'addercart' && $field->rows) {
          $total = 0;
          foreach ($field->rows as $row) {
            $math = (float) $row->option->math * $row->option->qty;
            $total += $math;
            $html .= "\r\n" . $this->imgEmail ($row->option) . ' ' . $row->option->label . ' ' . $row->option->qty . ' - ' . qf::formatPrice ($field, $math);
          }
          if ($total)
            $html .= "\r\n" . qf::formatPrice ($field, $total, 'QF_TOTAL') . "\r\n";
        }
        elseif ($field->teg == 'qftabs') {
          $options = $field->options;
          for ($n = 0; $n < sizeof ($options); $n++) {
            $html .= "\r\n" . $options[$n]->label . "\r\n";
            $html .= $this->getSimplRows ($field->data[$n]);
          }
        }
        elseif ($field->teg == 'customHtml') {
          $html .= $field->label . "\r\n";
        }
        elseif ($field->teg == 'customPhp') {
          if ($field->label) {
            $html .= $field->label . "\r\n";
          }
          $html .= $field->value . "\r\n";
        }
        elseif ($field->hide == 3) {
          if (isset($field->data) && ! empty($field->data)) {
            $html .= $this->getSimplRows ($field->data);
          }
        }
        else {
          $label = $this->findLable ($field);
          if ($label)
            $html .= $label . ' : ' . qf::get ('value', $field) . "\r\n";
          else
            $html .= qf::get ('value', $field) . "\r\n";

          if (isset($field->data) && ! empty($field->data)) {
            $html .= $this->getSimplRows ($field->data);
          }
        }
      }
    }
    return $html;
  }

  protected function findLable ($field) {
    if (qf::get ('label', $field)) {
      return $field->label;
    }
    $placeholder = qf::get ('placeholder', $field);
    return preg_replace ('/\s*\*$/', '', $placeholder);
  }


  public function writeStat ($project, $html) {
    global $wpdb;
    if ($project->params) {
      if (! qf::get ('history', $project->params)) {
        return true;
      }
    }

    $columns = array(
      'st_formid' => $project->id,
      'st_date' => gmdate ('Y-m-d H:i:s'),
      'st_form' => $html,
      'st_title' => Text::translate ($project->title),
      'st_ip' => \sanitize_text_field (@$_SERVER['HTTP_CLIENT_IP'] ?: @$_SERVER['HTTP_X_FORWARDED_FOR'] ?: @$_SERVER['REMOTE_ADDR']),
      'params' => '',
      'st_user' => (int) qf::user ()->get ('ID'),
      'st_status' => 0
    );

    $res1 = false;

    $res = $wpdb->insert ($wpdb->prefix . 'qf3_ps', $columns, ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d']);
    if ($res) {
      $res1 = $wpdb->insert_id;
    }

    $res2 = $wpdb->query ('UPDATE ' . $wpdb->prefix . 'qf3_projects SET hits = ( hits + 1 ) WHERE id = ' . (int) $project->id);

    if ($res1 && $res2) {
      return $res1;
    }
    return false;
  }

  public function sendMail ($project, $html, $statid = false) {
    $site_name = \get_option ('blogname');
    $admin_email = \get_option ('admin_email');

    $to = array();
    if ($this->email) {
      $to = $this->email;
    }
    elseif (qf::get ('toemail', $project->params)) {
      $arr = explode (',', $project->params->toemail);
      foreach ($arr as $ar) {
        $to[] = trim ($ar);
      }
    }
    else {
      $to = $admin_email;
    }

    if (is_numeric ($statid)) {
      $pre = 'id: ' . $statid . '. ';
    }
    else {
      $pre = 'tick: ' . time () . '. ';
    }

    if (qf::get ('subject', $project->params)) {
      $subject = $this->replacevars (Text::translate ($project->params->subject), $statid);
    }
    else {
      $subject = $pre . Text::translate ($project->title);
    }

    $message = $this->modifyHtml ($project, $html, $statid);


    if (qf::get ('tmpl', $project->params) == 'simple') {
      $headers[] = 'content-type: text/plain; charset=utf-8';
    }
    else {
      $headers[] = 'Content-type: text/html; charset=utf-8';
    }

    $replyto = $this->findlatest ('qfuseremail', FILTER_VALIDATE_EMAIL);
    $replytoname = $this->findlatest ('qfusername', FILTER_SANITIZE_SPECIAL_CHARS);

    if (! \is_email ($replyto)) {
      $replyto = false;
    }

    if ($replyto) {
      if ($replytoname) {
        $headers[] = 'Reply-To: ' . $replytoname . ' <' . $replyto . '>';
      }
      else {
        $headers[] = 'Reply-To: <' . $replyto . '>';
      }
    }

    $headers[] = 'From: ' . $site_name . ' <' . $admin_email . '>';


    \add_action ('phpmailer_init', array($this, 'attach_phpmailer'));

    $res = \wp_mail ($to, $subject, $message, $headers);

    \remove_action ('phpmailer_init', array($this, 'attach_phpmailer'));



    if ($res) {
      if ($this->back) {
        return $this->sendMailBack ($project, $html, $statid);
      }
      return true;
    }
    return false;
  }

  public function attach_phpmailer (PHPMailer $phpmailer) {
    foreach ($this->fileListToEmail as $file) {
      $phpmailer->addAttachment ($file['tmp_name'], $file['name']);
    }
  }

  public function sendMailBack ($project, $html, $statid) {
    $site_name = \get_option ('blogname');
    $admin_email = \get_option ('admin_email');

    $to = $this->back;

    if (qf::get ('subject', $project->params)) {
      $subject = $this->replacevars (Text::translate ($project->params->subject), $statid);
    }
    else {
      $subject = Text::translate ($project->title);
    }

    $message = $this->modifyHtml ($project, $html, $statid);

    if (qf::get ('tmpl', $project->params) == 'simple') {
      $headers[] = 'content-type: text/plain; charset=utf-8';
    }
    else {
      $headers[] = 'Content-type: text/html; charset=utf-8';
    }


    $headers[] = 'From: ' . $site_name . ' <' . $admin_email . '>';
    $headers[] = 'Reply-To: ' . $site_name . ' <' . $admin_email . '>';


    \add_action ('phpmailer_init', array($this, 'attach_phpmailer'));
    $res = \wp_mail ($to, $subject, $message, $headers);
    \remove_action ('phpmailer_init', array($this, 'attach_phpmailer'));
    return $res;
  }

  public function modifyHtml ($project, $html, $statid) {
    if ($this->modify) {
      return $this->modify;
    }

    if (qf::get ('start_text', $project->params)) {
      $html = $project->params->start_text . $html;
    }

    if (qf::get ('final_text', $project->params)) {
      $html = $html . $project->params->final_text;
    }

    $html = str_replace ('<img src="/', '<img src="' . ((! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/', $html);

    $this->modify = $this->replacevars ($html, $statid);

    return $this->modify;
  }

  public function replacevars ($html, $statid = '') {
    $username = $this->findlatest ('qfusername', FILTER_SANITIZE_SPECIAL_CHARS);
    if ($username) {
      $html = str_replace ('{replacerName}', $username, $html);
    }
    else {
      $html = str_replace ('{replacerName}', Text::_ ('QF_GUEST'), $html);
    }

    $html = str_replace ('{replacerId}', $statid, $html);
    $html = str_replace ('{replacerDate}', date ("m.d.Y"), $html);

    if (strpos ($html, '{replacerFieldId')) {
      $pattern = "/{replacerFieldId\s([\d.]+)}/";
      preg_match ($pattern, $html, $m);
      if (isset($m[1])) {
        $fieldval = '';
        $data = $this->linerData ($this->data);
        foreach ($data as $field) {
          if ($field->fieldid == $m[1]) {
            $fieldval = $field->value;
            break;
          }
        }
        $html = str_replace ($m[0], $fieldval, $html);
      }
    }

    return $html;
  }

  public function formredirect ($link, $msg, $msgtype) {
    if ($msg) {

        $set = qf::ses ()->get ('quickform', []);
        $set[$this->project->id][$msgtype] = $msg;
        qf::ses ()->set ('quickform', $set);

      setcookie ("qf3_message", "1");
    }
    header ("Location: $link");
    die();
  }
}