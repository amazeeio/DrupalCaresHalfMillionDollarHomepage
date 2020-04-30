<?php

// copy/paste/pray from https://stackoverflow.com/a/36753514

class DBSessionHandler implements SessionHandlerInterface {
  public function __construct ($session_start_options) {
    // print_r(['construct', $session_start_options]);
    // Set handler to overide SESSION
    session_set_save_handler(
      array($this, "open"),
      array($this, "close"),
      array($this, "read"),
      array($this, "write"),
      array($this, "destroy"),
      array($this, "gc")
      );
    register_shutdown_function('session_write_close');
    session_start($session_start_options);
  }

  public function open ($savepath, $id) {
    // print_r(['open', $savepath, $id]);
    $sql = 'SELECT `data` FROM sessions WHERE id = "' . mysqli_real_escape_string( $GLOBALS['connection'], $id) . '" LIMIT 1';    
    $result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));

    if ($result->num_rows == 1){
        return true;
    }

    return false;
  }
  
  public function read ($id) {
    // print_r(['read', $id]);
    $sql = 'SELECT `data` FROM sessions WHERE id = "' . mysqli_real_escape_string( $GLOBALS['connection'], $id) . '" LIMIT 1';
    $result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));
    $row = mysqli_fetch_array($result, MYSQLI_ASSOC);

    if ($result->num_rows > 0){
      return $row['data'];
    } else {
      return '';
    }
  }

  public function write ($id, $data) {
    // print_r(['write', $id, $data]);
    $id = mysqli_real_escape_string($GLOBALS['connection'], $id);
    $access = time();
    $data = mysqli_real_escape_string($GLOBALS['connection'], $data);
    $sql = 'REPLACE INTO sessions (id, access, data) VALUES ("' . $id . '", ' . $access . ', "' . $data . '")';
    // var_dump($sql);
    $result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));
    
    if ($result) {
      return true;
    } else {
      return false;
    }
  }

  public function destroy ($id) {
    // print_r(['destroy', $id]);
    $sql = 'DELETE FROM sessions WHERE id = "' . $id . '" LIMIT 1';
    $result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));

    if ($result) {
      return true;
    } else {
      return false;
    }
  }

  public function close () {
    return true;
  }

  public function gc ($max) {
    // print_r(['gc', $max]);
    $old = time() - $max;

    $sql = 'DELETE FROM sessions WHERE access < "' . $old;
    $result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));

    if ($result) {
      return true;
    } else {
      return false;
    }
  }

}
