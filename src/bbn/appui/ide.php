<?php
/**
 * Created by BBN Solutions.
 * User: Mirko Argentino
 * Date: 04/02/2017
 * Time: 15:56
 */
namespace bbn\appui;

class ide {

  use \bbn\models\tts\optional,
      \bbn\mvc\common;

  const BBN_APPUI = 'appui',
        BBN_PERMISSIONS = 'permissions',
        BBN_PAGE = 'page',
        IDE_PROJECTS = 'projects',
        IDE_PATH = 'ide',
        DEV_PATH = 'PATHS',
        PATH_TYPE = 'PTYPES',
       // FILES_PREF = 'files',
        OPENED_FILE = 'opened',
        RECENT_FILE = 'recent',
        THEME = 'theme';

  public static
    $backup_path,
    $backup_pref_path;

  private
    /** @var string $project name for get repositories*/
    $project = '',
    $repository_default = '',
    $origin = '';

  private static
    /** @var bool|int $ide_path */
    $ide_path = false,
    /** @var bool|int $dev_path */
    $dev_path = false,
    /** @var bool|int $path_type */
    $path_type = false,
    /** @var bool|int $files_pref */
    //$files_pref = false,
    /** @var bool|int $permissions */
    $permissions = false,
    /** @var bool|string $current_file */
    $current_file = false,
    /** @var bool|string $current_id */
    $current_id = false;


  protected
    /** @var \bbn\db $db */
    $db,
    /** @var \bbn\appui\options $options */
    $options,
    /** @var null|string The last error recorded by the class */
    $last_error,
    /** @var array MVC routes for linking with repositories */
    $routes = [],
    /** @var \bbn\user\preferences $pref */
    $pref,
    /** @var \bbn\appui\project $projects */
    $projects,
    $repositories_list = [];

  /**
   * Gets the ID of the development paths option
   *
   * @return int
   */
  private function _ide_path(){
    self::optional_init();
    if ( !self::$ide_path ){
      $this->_init_ide();
    }
    return self::$ide_path;
  }

  /**
   * Sets the root of the development paths option
   *
   * @param $id
   */
  private function _init_ide(){
    self::$ide_path = self::$option_root_id;
    self::$backup_path = $this->get_data_path('appui-ide').'backup/';
    self::$backup_pref_path = $this->get_data_path('appui-ide').'backup/preference/';
  }
/*
  private function _init_ide(){
    self::$ide_path = self::$option_root_id;
    self::$backup_path = \bbn\mvc::get_data_path('appui-ide').'backup/';
    self::$backup_pref_path = \bbn\mvc::get_data_path('appui-ide').'backup/preference/';
  }
*/
  /**
   * Gets the ID of the development paths option
   *
   * @return int
   */
  private function _dev_path(){
    if ( !self::$dev_path ){
      if ( $id = self::get_option_id(self::DEV_PATH) ){
        self::set_dev_path($id);
      }
    }
    return self::$dev_path;
  }

  /**
   * Sets the root of the development paths option
   *
   * @param $id
   */
  private static function set_dev_path($id){
    self::$dev_path = $id;
  }

  /**
   * Gets the ID of the page (permissions) option
   *
   * @return int
   */
  private function _permissions(){
    if ( !self::$permissions ){
      if ( $id = $this->options->from_code(self::BBN_PAGE, self::BBN_PERMISSIONS, self::BBN_APPUI) ){
        self::set_permissions($id);
      }
    }
    return self::$permissions;
  }

  /**
   * Sets the ID of the page (permissions) option
   *
   * @param int $id
   */
  private static function set_permissions($id){
    self::$permissions = $id;
  }

  /**
   * Function that returns corresponding bit with option id
   *
   * @param string $file path
   * @param string $id_user if set user id will return the result for that user otherwise the current one will return
   * @return array|null
   */
  private function get_bit_by_file(string $file, string $id_user = null): ?array
  {
    if ( !empty($file) &&
      !empty($this->db) &&
      !empty($this->pref) &&
      !empty($pref_arch = $this->pref->get_class_cfg())
    ){
      if ( is_null($id_user) ){
        $id_user = $this->pref->id_user;
      }
      return  $this->db->rselect([
        'table' => $pref_arch['tables']['user_options_bits'],
        'fields' => [],
        'where' => [
          'conditions' => [[
            'field' => $pref_arch['arch']['user_options_bits']['text'],
            'value' => $file
          ],[
            'field' => $pref_arch['arch']['user_options_bits']['id_user_option'],
            'value' => $id_user
          ]]
        ]
      ]);
    }
    return null;
  }

  /**
   * Sets the current file path
   *
   * @param string $file
   * @return string|false
   */
  private function set_current_file(string $file = null){

    if ( empty($file) ){
      self::$current_file = false;
      return false;
    }
    self::$current_file = $file;
    $this->real_to_url($file);
    $this->set_current_id();
    return self::$current_file;
  }

  /**
   * Sets the current file's ID
   *
   * @param string $file
   * @return string
   */
  private function set_current_id(string $file = null){
    self::$current_id = false;
    if ( empty($file) ){
      $file = self::$current_file;
    }

    if ( !empty($file) ){
      if ( $id = $this->real_to_url($file) ){
        self::$current_id = $id;
      }
    }
    return self::$current_id;
  }

  /**
   * Resets the current file's info
   */
  private function reset_current(){
    $this->set_current_file();
  }

  /**
   * Checks if the file is a superior super-controller and returns the corrected name and path
   *
   * @param string $tab The tab'name from file's URL
   * @param string $path The file's path from file's URL
   * @return array
   */
  private function superior_sctrl(string $tab, string $path = ''){
    if ( ($pos = strpos($tab, '_ctrl')) > -1 ){
      if ( ($pos === 0) ){
        $path = '';
      }
      else {
        // Fix the right path
        $bits = explode('/', $path);
        $count = \strlen(substr($tab, 0, $pos));
        if ( !empty($bits) ){
          foreach ( $bits as $i => $b ){
            if ( ($i + 1) > $count ){
              unset($bits[$i]);
            }
          }
          $path = implode('/', $bits). '/';
        }
      }
      // Fix the tab's name
      $tab = '_ctrl';
    }
    return [
      'tab' => $tab,
      'path' => $path,
      'ssctrl' => $count ?? 0
    ];
  }

  /**
   * Deletes all files' options of a folder and returns an array of these files.
   *
   * @param string $d The folder's path
   * @return array
   */
  /*private function rem_dir_opt(string $d){
    $sub_files = $this->fs->scan($d);
    $files = [];
    foreach ( $sub_files as $sub ){
      if ( $this->fs->is_file($sub) ){
        // Add it to files to be closed
        array_push($files, $this->real_to_url($sub));
        // Remove file's options
        $this->options->remove($this->options->from_code($this->real_to_id($sub), $this->_files_pref()));
      }
      else{
        $f = $this->rem_dir_opt($sub);
        if ( !empty($f) ){
          $files = array_merge($files, $f);
        }
      }
    }
    return $files;
  }*/

  private function check_normal(array $cfg, array $rep, string $path){
    if ( !empty($cfg) && !empty($path) && !empty($cfg['name']) ){

      $old = $new = $path;
      if ( !empty($cfg['path']) && ($cfg['path'] !== './') ){
        $old .= $cfg['path'] . (substr($cfg['path'], -1) !== '/' ? '/' : '');
      }
      if ( !empty($cfg['new_path']) && ($cfg['new_path'] !== './') ){
        $new .= $cfg['new_path'] . (substr($cfg['new_path'], -1) !== '/' ? '/' : '');
      }
      if ( !empty($cfg['is_file']) &&
        ( ( !empty($cfg['ext']) && (\bbn\x::find($rep['extensions'], ['ext' => $cfg['ext']]) === false) ) ||
          ( !empty($cfg['new_ext']) && (\bbn\x::find($rep['extensions'], ['ext' => $cfg['new_ext']]) === false) )
        )
      ){
        return false;
      }

      $old .= $cfg['name'] . (!empty($cfg['is_file']) ? '.' . $cfg['ext'] : '');

      $new .= ($cfg['new_name'] ?? '') .
        (!empty($cfg['is_file']) && !empty($cfg['new_ext']) ? '.' . $cfg['new_ext'] : '');

      if ( ($path !== $new) && !empty($this->fs->exists($new)) ){
        $this->error("The new file|folder exists: $new");
        return false;
      }
      if ( $this->fs->exists($old) ){
        return [
          'old' => $old,
          'new' => ($path === $new) ? false : $new
        ];
      }
    }

    return false;
  }

  private function check_mvc(array $cfg, array $rep, string $path){
    $todo = [];
    if ( !empty($cfg) &&
      !empty($rep) &&
      !empty($rep['tabs']) &&
      !empty($cfg['name']) &&
      isset($cfg['is_file'], $path)
    ){
      if ( !empty($rep['alias_code']) && ($rep['alias_code'] === 'bbn-project') ){
          $path .= 'mvc/';
      }
      // Each file associated with the structure (MVC case)
      foreach ( $rep['tabs'] as $i => $tab ){
        // The path of each file
        $tmp = $path;
        if ( !empty($tab['path']) ){
          $tmp .= $tab['path'];
        }

        $old = $new = $tmp;

        if ( !empty($cfg['path']) && ($cfg['path'] !== './') ){
          $old .= (($cfg['path'] === 'mvc/') ?  '' : $cfg['path']) . (substr($cfg['path'], -1) !== '/' ? '/' : '');
        }
        if ( !empty($cfg['new_path']) &&  ($cfg['new_path'] !== './') ){
          $new .= (($cfg['new_path'] === 'mvc/') ?  '' : $cfg['new_path']) . (substr($cfg['new_path'], -1) !== '/' ? '/' : '');
        }

        if ( ($tab['url'] !== '_ctrl') && !empty($tab['extensions']) ){
          $old .= $cfg['name'];
          $new .= $cfg['new_name'] ?? '';
          $ext_ok = false;
          if ( !empty($cfg['is_file']) ){
            foreach ( $tab['extensions'] as $k => $ext ){
              if ( $k === 0 ){
                if ( !empty($cfg['new_name']) && $this->fs->is_file($new.'.'.$ext['ext']) ){
                  $this->error("The new file exists: $new.$ext[ext]");
                  return false;
                }
              }

              if ( $this->fs->is_file($old.'.'.$ext['ext']) ){
                $ext_ok = $ext['ext'];
              }
            }
          }
          if ( !empty($cfg['is_file']) && empty($ext_ok) ){
            continue;
          }

          $old .= !empty($cfg['is_file'])  ? '.' . $ext_ok : '';
          $new .= !empty($cfg['is_file']) ? '.' . $tab['extensions'][0]['ext'] : '';

          if ( !empty($cfg['new_name']) && ($new !== $tmp) && !empty($this->fs->exists($new)) ){
            $this->error("The new file|folder exists.");
            return false;
          }

          if ( $this->fs->exists($old) ){
            array_push($todo, [
              'old' => \bbn\str::parse_path($old),
              'new' => (empty($cfg['new_name']) || ($new === $tmp)) ? false : \bbn\str::parse_path($new),
              'perms' =>  $tab['url'] === 'php' //$i === 'php'
            ]);
          }
        }
      }
    }

    return $todo;
  }

  /**
   * Delete the bits that all have the same id_option
   *
   * @param string $id_option
   * @return boolean|null
   */
  private function delete_bits_preferences(string $id_option): ?bool
  {
    if ( \bbn\str::is_uid($id_option) && !empty($bits = $this->pref->get_bits_by_id_option($id_option)) ){
      $delete_bits = true;
      foreach ( $bits as $bit ){
        if ( is_null($this->pref->delete_bit($bit['id'])) ){
          $delete_bits = false;
          break;
        }
      }
      return $delete_bits;
    }
    return null;
  }

  /**
   * Delete a component vue or all folder
   *
   * @param array $cfg component info
   * @return bool
   */
  private function delete_component(array $cfg){
    if ( !empty($cfg) && !empty($cfg['repository']) ){
      $path = $this->get_root_path($cfg['repository']['name']);
      if ( !empty($cfg['path']) && !empty($cfg['is_file']) ){
        if ( empty($this->fs->delete($path.$cfg['path'])) ){
          return false;
        }
        return true;
      }
      //case of context menu
      else{
        $folder = !empty($cfg['is_file']) ? false : true;
        $ctrl_error = false;
        if ( !empty($cfg['repository']['path']) && !empty($cfg['path']) && !empty($cfg['name']) ){
          //all
          if ( empty($cfg['only_component']) ){
            $component = $path.$cfg['path'].$cfg['name'];
            if ( empty($this->fs->delete($component)) ){
              return false;
            }
            return true;
          }
          else{
            if ( !empty($cfg['repository']['tabs']) && is_array($cfg['repository']['tabs']) ){
              foreach( $cfg['repository']['tabs'] as $tab ){
                if ( empty($ctrl_error) ){
                  if ( is_array($tab['extensions']) ){
                    foreach( $tab['extensions'] as $a ){
                      $component = $path.$cfg['path'].$cfg['name'].'/'.$cfg['name'].'.'.$a['ext'];
                      if( !empty($this->fs->exists($component)) ){
                        if ( empty($this->fs->delete($component)) ){
                          $ctrl_error = true;
                          break;
                        }
                      }
                    }
                  }
                }
                else{
                  return false;
                }
              }
              return true;
            }
          }
        }
      }
    }
    return false;
  }

  /**
   * Function for move component vue
   *
   * @param array $cfg
   * @param array $rep
   * @param string $path
   * @return boolean
   */
  private function move_component(array $cfg, array $rep, string $path){
    $ele = $this->check_normal($cfg, $rep, $path);
    if ( !empty($ele) && is_array($ele) && empty($this->fs->move($ele['old'], dirname($ele['new']))) ){
      return false;
    }
    return true;
  }

  /**
   * Copy a component vue or all folder
   *
   * @param array $cfg component info
   * @return bool
   */
  private function copy_component(array $cfg){
    if ( !empty($cfg) &&
      !empty($cfg['path']) &&
      !empty($cfg['new_path']) &&
      !empty($cfg['name']) &&
      !empty($cfg['new_name']) &&
      !empty($cfg['repository']) &&
      is_array($cfg['repository'])
    ){
      $ctrl_error = false;
      if ( !empty($cfg['repository']['path']) ){
        // get root in absolute path
        $path = $this->get_root_path($cfg['repository']['name']);
        $old_folder_component = $path.$cfg['path'].$cfg['name'];
        $new_folder_component = $path.$cfg['new_path'].$cfg['new_name'];
        //copy only component parent
        if ( !empty($cfg['only_component']) ){
          if( !empty($this->fs->create_path($new_folder_component)) ){
            if ( is_array($cfg['repository']['tabs']) &&
              count($cfg['repository']['tabs'])
            ){
              foreach( $cfg['repository']['tabs'] as $ele ){
                if ( empty($ctrl_error) ){
                  if ( !empty($ele['extensions']) && is_array($ele['extensions']) ){
                    foreach($ele['extensions'] as $a){
                      $old_component = $old_folder_component.'/'.$cfg['name'].'.'.$a['ext'];
                      $new_component = $new_folder_component.'/'.$cfg['new_name'].'.'.$a['ext'];
                      if( !empty($this->fs->exists($old_component)) && empty($this->fs->exists($new_component)) ){
                        if ( empty($this->fs->copy($old_component, $new_component)) ){
                          $ctrl_error = true;
                          break;
                        }
                      }
                    }
                  }
                }
                else{
                  $this->error("Error during the copy of component");
                  return false;
                }
              }
            }
            return true;
          }
        }//copy only component who hasn't children
        else{
          if ( empty($this->fs->copy($old_folder_component, $new_folder_component)) ){
            //case error
            $ctrl_error = true;
          }
          if ( empty($ctrl_error) &&
            empty($cfg['is_file']) &&
            !empty($cfg['component_vue'])
          ){
            if ( is_array($cfg['repository']['tabs']) &&
              count($cfg['repository']['tabs'])
            ){
              foreach( $cfg['repository']['tabs'] as $tab ){
                if ( empty($ctrl_error) ){
                  if ( !empty($tab['extensions']) && is_array($tab['extensions']) ){
                    foreach( $tab['extensions'] as $a ){
                      $old_component = $new_folder_component.'/'.$cfg['name'].'.'.$a['ext'];
                      $new_component = $new_folder_component.'/'.$cfg['new_name'].'.'.$a['ext'];
                      if( !empty($this->fs->exists($old_component)) && empty($this->fs->exists($new_component)) ){
                        if ( empty($this->fs->rename($old_component, $cfg['new_name'].'.'.$a['ext'])) ){
                          $ctrl_error = true;
                          break;
                        }
                      }
                    }
                  }
                }
                else{
                  $this->error("Error during the copy component");
                  return false;
                }
              }
            }
            return true;
          }
        }
      }
    }
    return false;
  }


  /**
   * Rename a component vue or all folder
   *
   * @param array $cfg component info
   * @return bool
   */
  private function rename_component(array $cfg){
    if ( !empty($cfg) &&
      !empty($cfg['path']) &&
      !empty($cfg['new_path']) &&
      !empty($cfg['name']) &&
      !empty($cfg['new_name']) &&
      !empty($cfg['repository']) &&
      is_array($cfg['repository'])
    ){
      if ( !empty($cfg['repository']['path']) ){
        $path = $this->get_root_path($cfg['repository']['name']);
        $ctrl_error = false;
        $old_folder_component = $path.$cfg['path'].$cfg['name'];
        $new_folder_component = $path.$cfg['path'].$cfg['new_name'];
        //folder
        if ( empty($cfg['only_component']) ){
          if ( empty($this->fs->rename($old_folder_component,$cfg['new_name'])) ){
            $ctrl_error = true;
            $this->error("Error during the rename component");
          }
        }
        else{
          if ( empty($this->fs->is_dir($new_folder_component)) &&
            empty($this->fs->create_path($new_folder_component))
          ){
            $ctrl_error = true;
          }
        }
        //case rename component
        if ( empty($ctrl_error) &&
          empty($cfg['is_file']) &&
          !empty($cfg['component_vue'])
        ){
          if ( is_array($cfg['repository']['tabs']) &&
            count($cfg['repository']['tabs'])
          ){
            foreach( $cfg['repository']['tabs'] as $tab ){
              if ( empty($ctrl_error) ){
                if ( is_array($tab['extensions']) &&
                  count($tab['extensions'])
                ){
                  foreach( $tab['extensions'] as $a ){
                    $old_file = $cfg['name'].'.'.$a['ext'];
                    $new_file = $cfg['new_name'].'.'.$a['ext'];
                    $old_component = $old_folder_component .'/'.$old_file;
                    $new_component = $new_folder_component.'/'.$new_file;
                    if(  empty($this->fs->exists($new_component)) ){
                      //if direct component
                      if ( !empty($cfg['only_component']) ){
                        if ( !empty($this->fs->move($old_component, $new_folder_component)) ){
                          if ( empty($this->fs->rename($new_folder_component.'/'.$old_file, $new_file)) ){
                            $ctrl_error = true;
                            $this->error("Error during the rename component");
                            return false;
                          }
                        }
                      }
                      else{
                        if ( !empty($this->fs->exists($new_folder_component.'/'.$old_file)) ){
                          if ( empty($this->fs->rename($new_folder_component.'/'.$old_file, $new_file)) ){
                            $ctrl_error = true;
                            $this->error("Error during the rename component");
                            return false;
                          }
                        }
                      }

                    }
                  }
                }
              }
              else{
                break;
              }
            }
          }
        }
        if( !empty($ctrl_error) ){
          $this->error("Impossible to the file|folder.");
          return false;
        }
        else{
          return true;
        }
      }
    }
    return false;
  }

  /**
   * Function who return path relative for backup history or preferences
   *
   *
   */
  private function get_path_backup(array $file){
    //if in the case of a rescue of _ctrl
    if ( $file['tab'] === "_ctrl" ){
      if ( isset($file['ssctrl']) && is_numeric($file['ssctrl']) ){
        $backup_path = self::$backup_path . $file['repository']['path']. '/' .$file['filePath'].'/'.$file['tab'] . '/';
      }
    }
    else {
      $backup_path = self::$backup_path;
      //there isn't reposiotry
      if ( !isset($file['repository']) ){
        $backup_path .= dirname($file['full_path']);
        $fn = \bbn\str::file_ext($file['full_path'],1);
        $terminal_path =  ($file['tab'] ?: $fn[1]) . '/';
        $relative_path = $fn[0]. '/__end__/';
        $backup_path .= '/'. $relative_path;
      }
      else {
        $terminal_path = ($file['tab'] ?: $file['extension']) . '/';
        $relative_path = $file['repository']['root'].'/'.$file['repository']['code'].'/src/'.$file['path'].'/'.$file['filename'].(!empty($file['component_vue']) ? '/'.$file['filename'].'/' : '/' ) .'__end__/';
        $backup_path .= $relative_path;
      }
    }
    if ( isset($backup_path) ){
      return [
        'absolute_path' => \bbn\str::parse_path($backup_path),
        'path_preference' => \bbn\str::parse_path($backup_path.$file['tab'].'/'),
        'path_history' => \bbn\str::parse_path($backup_path.$terminal_path)
      ];
    }
    return false;
  }


  /**
   * Function who create,change or delete file preference
   *
   * @param array $file
   * @param array $state
   * @param string $type
   * @return string|boolean
   */
  private function backup_preference_files(array $file, array $state, string $type=''){
    $state = json_encode($state);
    $backup_path = $this->get_path_backup($file);

    $backup = $backup_path['path_preference'].$file['filename'].'.json';
\bbn\x::log([$backup, $this->get_data_path('appui-ide')],'pref');
    if ( !empty($backup_path) ){
      if ( ($type === 'create') ){
        if ( $this->fs->create_path(dirname($backup)) &&
          $this->fs->put_contents($backup, $state) ){
          return $backup;
        }
      }
      elseif ( ($type === 'change') ){
        if ( $this->fs->exists($backup) &&
          empty($this->fs->delete($backup, 1))
        ){
          return false;
        }
        if ( $this->fs->create_path(dirname($backup)) &&
          $this->fs->put_contents($backup, $state)
        ){
          return $backup;
        }
      }
      elseif ( $type === 'delete' && $this->fs->delete($backup, 1) ){
        return $backup;
      }
    }
    return false;
  }

  /**
   * create|delete history file
   *
   * @param array $file
   * @param string $type
   * @return void
   */
  private function backup_history(array $file, string $type='' ){
    if ( !empty($backup_path = $this->get_path_backup($file)) ){
      $backup = $backup_path['path_history'] . date('Y-m-d_His') . '.' .$file['extension'];
      if ( ($type === 'create')  &&  $this->fs->is_file(self::$current_file) ){
        $this->fs->create_path(dirname($backup));
        $this->fs->copy(self::$current_file, $backup);
      }
      elseif ( $type === 'delete' ){
        $this->fs->delete($backup_path['path_history'], 1);
      }
    }
  }



  /**
   * Renames|movie|delete a file or a folder of the backup.
   *
   * @param array $cfg The components info
   * @param string $ope The operation type (rename, copy)
   * @return bool
   */
  private function manager_backup_components(array $cfg, string $case ){
    if ( !empty($cfg['is_component']) ){
      $component_type = $this->get_type('components');
      $tabs = $component_type['tabs'];
      $backup_path = self::$backup_path . $cfg['repository']['name'].'/src/';
      $old_folder_component = $backup_path.$cfg['path'].$cfg['name'];
      //file preferences json in not correctly name
      if ( $this->fs->is_dir($old_folder_component) ){
        switch ( $case ){
          case 'move':
            if ( $this->fs->is_dir($backup_path.$cfg['new_path']) &&
              empty($this->fs->move($old_folder_component, $backup_path.$cfg['new_path']))
            ){
              $this->error("Error during the folder backup move: old -> $old_folder_component" );
              return false;
            }
            break;
          case 'copy':
            //copy general folder
            if ( !empty($this->fs->copy($old_folder_component, $backup_path.$cfg['new_path'].$cfg['new_name'])) ){
              //if exist component into new general folder to do rename
              if ( $this->fs->exists($backup_path.$cfg['new_path'].$cfg['new_name']) ){
                if (  !empty($this->fs->rename($backup_path.$cfg['new_path'].$cfg['new_name'].'/'.$cfg['name'], $cfg['new_name']))  ){
                  //if exist old file preference rename file
                  foreach( $tabs as $tab ){
                    $old_file_preferences = $backup_path.$cfg['new_path'].$cfg['new_name'].'/'.$cfg['new_name'].'/__end__/'.$tab['path'].$cfg['name'].'.json';
                    if ( $this->fs->is_file($old_file_preferences) ){
                      if ( empty($this->fs->rename($old_file_preferences, $cfg['new_name'].'.json')) ){
                        $this->error("Error during rename file preferences for component");
                        return false;
                      }
                      break;
                    }
                  }
                }
                else {
                  $this->error("Error during the component backup copy: old -> $old_folder_component");
                  return false;
                }
              }
            }
            else {
              $this->error(_("Error during the component backup copy: old ->"). $old_folder_component);
              return false;
            }
            return true;
            break;
          case 'rename':
           //rename general folder backup
            if ( !empty($this->fs->rename($old_folder_component, $cfg['new_name'])) ){
              if ( $this->fs->exists($backup_path.$cfg['path'].$cfg['new_name'].'/'.$cfg['name']) &&
                empty($this->fs->rename($backup_path.$cfg['path'].$cfg['new_name'].'/'.$cfg['name'], $cfg['new_name']))
              ){
                $this->error(_("Error during the folder backup rename copmonent"));
                return false;
              }//rename file preferences
              else{
                foreach( $tabs as $tab ){
                  $old_file_preferences = $backup_path.$cfg['path'].$cfg['new_name'].'/'.$cfg['new_name'].'/__end__/'.$tab['path'].$cfg['name'].'.json';
                  if ( $this->fs->is_file($old_file_preferences) ){
                    if ( empty($this->fs->rename($old_file_preferences, $cfg['new_name'].'.json')) ){
                      $this->error("Error during rename file preferences for component");
                      return false;
                    }
                    break;
                  }
                }
              }
            }
            return true;
            break;
          case 'delete':
            if ( empty($this->fs->delete($old_folder_component)) ){
              $this->error("Error during the component backup delete");
              return false;
            }
            return true;
            break;
        }
      }
    }
    return false;
  }

  /**
   * Renames|movie|copy|delete a file or a folder of the backup and file preferernces.
   *
   * @param array $path paths of file|folder, old and new
   * @param array $cfg The file|folder info
   * @param string $ope The operation type (rename, copy)
   * @return bool
   */
  private function manager_backup(array $path,  array $cfg, string $case ){
    //configuration path for backup
    $backup_path = self::$backup_path . $cfg['repository']['path'].'/src';
    // for case of copy the type is included in path and new_path
    if ( !empty($path['old']) &&
      !empty($cfg)
    ){
      if ( !empty($cfg['is_project']) &&
        !empty($cfg['type']) &&
        ($case !== 'copy')
      ){
        $type = $cfg['type'];
      }

      $old_backup = $backup_path.( isset($type) ? '/'.$type .'/' : '/');

      $path_old =  explode("/", $path['old']);
      if ( is_array($path_old) && count($path_old) ){
        $path_old = array_pop($path_old);
        $path_old = explode(".",$path_old)[0];
        $old_backup .= $cfg['path'] .'/'.$path_old;
        $old_backup = str_replace('//','/', $old_backup);
      }
      else{
        $this->error("Error during the file|folder backup delete: old -> $old_backup");
      }
      //CASE MOVE, RENAME and COPY
      if ( (($case === 'move') || ($case === 'rename') || ($case === 'copy')) &&
        !empty($path['new'])
      ){
        $new_backup = $backup_path.'/'.(isset($type) ? $type .'/' : '');
        $new_backup .= ($case === 'rename' ? $cfg['path'] : $cfg['new_path']).'/'.\bbn\str::file_ext($path['new'], 1)[0];
        $new_backup = str_replace('//','/', $new_backup);
      }
    }

    if ( isset($old_backup) && $this->fs->is_dir($old_backup) ){
      // if it isn't a folder
      if ( !$this->fs->is_dir($path['old']) && !$this->fs->is_dir($path['new']) ){
        //move or rename
        if ( ($case === 'move') || ($case === 'rename') ){
          //if exist a backup folder
          //if the folder containing the backup does not exist, it is created
          if ( !$this->fs->exists($new_backup) ){
            if ( empty($this->fs->create_path($new_backup)) ){
              $this->error("Error during the file|folder backup create new -> $new_backup");
              return false;
            }
          }
          if ( $this->fs->is_dir($new_backup) &&
            empty($this->fs->move($old_backup . "/__end__", $new_backup))
          ){
            $this->error("Error during the file|folder backup move: old -> $old_backup , new -> $new_backup");
            return false;
          }
          else {
            if ( $this->fs->is_dir($old_backup) &&  empty($this->fs->delete($old_backup)) ){
              $this->error("Error during the file|folder backup delete: old -> $old_backup");
              return false;
            }
            //for file json preferences
            // if not rename file preferences and exist
            $old_file_preferences = $new_backup."/__end__/".basename($path['old'], \bbn\str::file_ext($path['old'],1)[1]).'json';
            if ( $this->fs->exists($old_file_preferences) ){
              //get new name for file preference
              $new_file_preferences = basename($path['new'], \bbn\str::file_ext($path['new'],1)[1]).'json';
              if ( empty($this->fs->rename($old_file_preferences, $new_file_preferences )) ){
                $this->error("Error during the file|folder backup delete: old -> $old_backup");
                return false;
              }
            }
          }
        }//case delete
        elseif ( $case === 'delete' ){
          if ( empty($this->fs->delete($old_backup)) ){
            $this->error("Error during the file backup delete: old -> $old_backup");
            return false;
          }
        }//case in copy
        elseif ( ($case === 'copy') &&
          !$this->fs->exists($new_backup) &&
          empty($this->fs->copy($old_backup, $new_backup))
        ){
          $this->error(_("Error during the file backup copy: old ->"). $old_backup);
          return false;
        }
      }//case folder
      else{
        //case copy
        if ( ($case === 'copy') && empty($this->fs->copy($old_backup, $new_backup)) ){
          $this->error("Error during the folder backup copy: old -> $old_backup");
          return false;
        }//case rename
        elseif ( ($case === 'rename') && empty($this->fs->rename($old_backup,  basename($new_backup))) ){
          $this->error("Error during the folder rename old -> $old_backup , new -> $new_backup");
          return false;
        }
        //case delete
        elseif ( ($case === 'delete') && empty($this->fs->delete($old_backup)) ){
          $this->error("Error during the folder backup delete: old -> $old_backup");
          return false;
        }//case move
        elseif ( ($case === 'move') &&
          $this->fs->is_dir(dirname($new_backup)) &&
          empty($this->fs->move($old_backup, dirname($new_backup)))
        ){
          $this->error("Error during the folder backup move: old -> $old_backup" );
          return false;
        }
      }
      return true;
    }
    return false;
  }

  /**
   * Renames|copies a file or a folder.
   *
   * @param array $cfg The file|folder info
   * @param string $ope The operation type (rename, copy)
   * @return bool
   */
  private function operations(array $cfg, string $ope){
    //die(var_dump($cfg, $ope));
    if ( is_string($ope) &&
      !empty($cfg['repository']) &&
      !empty($cfg['name']) &&
      isset($cfg['is_mvc'], $cfg['is_file'], $cfg['path']) &&
      ( ( $ope === 'delete' ) ||
        ( ( $ope !== 'delete' ) &&
          ( ( isset($cfg['new_name']) &&
              ($cfg['name'] !== $cfg['new_name'])
            ) ||
            ( isset($cfg['new_path']) &&
              ($cfg['path'] !== $cfg['new_path'])
            ) ||
            ( !empty($cfg['is_file']) &&
              isset($cfg['ext'], $cfg['new_ext']) &&
              ($cfg['ext'] !== $cfg['new_ext'])
            )
          )
        )
      )
    ){
      $rep = $cfg['repository'];

      $path = $this->get_root_path($rep['name']);
      if ( $ope === 'rename' ){
        $cfg['new_path'] = $cfg['path'];
      }

      // Normal file|folder
      if ( empty($cfg['is_component']) &&
        empty($cfg['is_mvc']) &&
        ( empty($cfg['is_file']) ||
          ( !empty($cfg['is_file']) &&
            !empty($rep['extensions'])
          )
        )
      ){
        $f = $this->check_normal($cfg, $rep, $path);
        if ( $ope === 'move' && !empty($cfg['is_file']) ){
          $f['new'] = $f['new']. '.'.$cfg['ext'];
        }
        if ( $f &&
          // Copy
          ((($ope === 'copy') &&
              $this->fs->copy($f['old'], $f['new'])
            ) ||
            // Rename
            (($ope === 'rename') &&
            //rename or file or folder, in case of file addded extension
              $this->fs->rename($f['old'], $cfg['new_name'].($this->fs->is_file($f['old'])  ? '.'.$cfg['ext'] : ''))
            ) ||
            //Move
            (($ope === 'move') &&
              $this->fs->move($f['old'], $f['new'])
            ) ||
            // Delete
            (($ope === 'delete') &&
              $this->fs->delete($f['old'])
              /** @todo Remove backups */
            )
          )
        ){
          //for rename and move backup
           return $this->manager_backup($f, $cfg, $ope);
          //return true;
        }
      }
      // MVC
      elseif (
        !empty($rep['tabs']) &&
        (($rep['alias_code'] === 'mvc') || ($rep['alias_code'] === 'bbn-project')) &&
        !empty($cfg['is_mvc'])
      ){
        if ( ($rep['alias_code'] === 'bbn-project') &&
          ($ope === 'delete') &&
          !empty($cfg['active_file'])
        ){
          if ( empty($this->fs->delete($path.$cfg['path'])) ){
            $this->error("Error during the file|folder delete: $t[old]");
            return false;
          }
          return true;
        }
        if ( $todo = $this->check_mvc($cfg, $rep, $path) ){
          foreach ( $todo as $t ){
            //case rename and move
            if ( ($ope === 'rename') || ($ope === 'move') ){
               // Change permissions
              //case rename
              if ( $ope === 'rename' ){
                //case rename file
                if ( $this->fs->is_file($t['old']) ){
                  $new_name = basename($t['new']);
                }
                //case rename folder
                else{
                  $new_name = $cfg['new_name'];
                }
                if ( empty($this->fs->rename($t['old'], $new_name)) ){
                  $this->error("Error during the file|folder move: old -> $t[old] , new -> $t[new]");
                  return false;
                }
                if( empty( $this->real_to_perm($t['old']) ) &&
                  !empty($cfg['is_file']) &&
                  (strpos($t['old'], '/mvc/public/') !== false)
                ){
                  if ( !$this->create_perm_by_real($t['old']) ){
                    return $this->error(_("Impossible to create the option for rename"));
                  }
                }
                if ( !empty($t['perms']) &&
                  !$this->change_perm_by_real($t['old'], $t['new'], empty($cfg['is_file']) ? 'dir' : 'file')
                ){
                  if( !empty( $this->real_to_perm($t['old'])) ){
                    $this->error("Error during the file|folder permissions change: old -> $t[old] , new -> $t[new]");
                    return false;
                  }
                }
              }//case move
              else{
                if ( empty($this->fs->move($t['old'], dirname($t['new']))) ){
                  $this->error("Error during the file|folder move: old -> $t[old] , new -> $t[new]");
                  return false;
                }
                if( !empty( $this->real_to_perm($t['old']) ) &&
                    !empty($cfg['is_file']) && (strpos($t['old'], '/mvc/public/') !== false)
                  ){
                  if ( !$this->create_perm_by_real($t['old']) ){
                    return $this->error(_("Impossible to create the option for move"));
                  }
                }
                if ( !empty($t['perms']) &&
                  !$this->move_perm_by_real($t['old'], $t['new'], empty($cfg['is_file']) ? 'dir' : 'file')
                ){
                  $this->error("Error during the file|folder permissions change: old -> $t[old] , new -> $t[new]");
                  return false;
                }
              }
              //move,copy  or rename preference and history
              $this->manager_backup($t, $cfg, $ope);
            }
            // Copy
            elseif ( $ope === 'copy' ){
              if ( empty($this->fs->copy($t['old'], $t['new'])) ){
                $this->error("Error during the file|folder copy: old -> $t[old] , new -> $t[new]");
                return false;
              }
              // Create permissions
              if ( !empty($t['perms']) && !$this->create_perm_by_real($t['new'], empty($cfg['is_file']) ? 'dir' : 'file') ){
                $this->error("Error during the file|folder permissions create: $t[new]");
                return false;
              }
              //Copy preferences and history
              $this->manager_backup($t, $cfg, $ope);
            }
            //case Delete
            elseif ( $ope === 'delete' ){
              if ( empty($this->fs->delete($t['old'])) ){
                $this->error("Error during the file|folder delete: $t[old]");
                return false;
              }
              // Delete permissions
              if ( !empty($t['perms']) ){
                $this->delete_perm($t['old']);
              }
              ///delete backup and file preference
              $this->manager_backup($t, $cfg, $ope);
            }
          }
          return true;
        }
      }
      //case components
      else if( !empty($cfg['is_component']) ){
        // DELETE COMPONENT
        if ( ($ope === 'delete') && empty($this->delete_component($cfg)) ){
          return false;
        }
        // COPY COMPONENT
        elseif ( ($ope === 'copy') && empty($this->copy_component($cfg)) ){
          return false;
        }
        // RENAME COMPONENT
        elseif ( ($ope === 'rename') && empty($this->rename_component($cfg)) ){
          return false;
        }
        //MOVE COMPONENT
        elseif ( ($ope === 'move') && empty($this->move_component($cfg, $rep, $path)) ){
          return false;
        }
        //if the operation was successful then the backup and history will be managed
        $this->manager_backup_components($cfg, $ope);
        return true;
      }
    }
    else{
      $this->error("Impossible to $ope the file|folder.");
      return false;
    }
  }

  /**
   * Sets the last error as the given string.
   *
   * @param string $st
   * @return string
   */
  protected function error(string $st){
    \bbn\x::log($st, "ide");
    $this->last_error = $st;
    return $this->last_error;
  }



  /**
   * ide constructor.
   *
   * @param \bbn\appui\options $options
   * @param $routes
   * @param \bbn\user\preferences $pref
   */
  public function __construct(\bbn\db $db,  \bbn\appui\options $options, $routes, \bbn\user\preferences $pref, string $project = '', string $plugin = 'appui-ide'){
    $this->db = $db;
    $this->options = $options;
    $this->routes = $routes;
    $this->pref = $pref;
    $this->fs = new \bbn\file\system();
    $this->origin = $plugin;
    $this->set_project($project);
  }

  public function init(){
    if  ( !empty($this->project) ){
      $this->repository_default = '';
      $this->repositories = $this->get_repositories();
      foreach( $this->repositories as $i => $rep ){
        if ( empty($this->repository_default) ){
          $this->repository_default = $rep['name'];
        }
        $this->repositories[$i]['root_path'] = $this->get_root_path($rep['name']);
        if ( !empty($rep['default']) ){
          $this->repository_default = $rep['name'];
        }
      }
    }
  }

  public function get_default_repository(){
    return $this->repository_default;
  }

  public function set_project(string $project){
    $project_name = false;
    //case project is uid
    if ( \bbn\str::is_uid($project) && !empty($rep = $this->options->option($project)) ){
      $this->projects = new \bbn\appui\project($this->db, $project);
      $project_name = $rep['name'];
    }
    //case project is name
    elseif( (strlen($project) > 0) && !empty($opt = $this->options->from_code($project, self::IDE_PROJECTS, self::BBN_APPUI)) ){
      $this->projects = new \bbn\appui\project($this->db, $opt);
      $project_name = $project;
    }
    // case project is not defined get default
    elseif ( defined('BBN_PROJECT_NAME') && !empty($opt = $this->options->from_code(constant('BBN_PROJECT_NAME'), self::IDE_PROJECTS, self::BBN_APPUI)) ){
      $this->projects = new \bbn\appui\project($this->db, $opt);
      $project_name = constant('BBN_PROJECT_NAME');
    }
    $this->project = $project_name;
    if ( $project_name && !empty($this->projects) ){
      $this->init();
      $this->_ide_path();
    }
    return $project_name;
  }

  public function is_project(string $url){
    $rep = $this->repository_from_url($url);
    //$repository = $this->repositories($rep);
    $repository = $this->repository($rep);
    if ( is_array($repository) && !empty($repository) ){
      if ( ($repository['alias_code'] === 'bbn-project') ){
        return true;
      }
    }
    return false;
  }

  public function get_origin(){
    return $this->origin;
  }

  /**
   * Checks if a repository is a Component manager
   *
   * @param string $rep
   * @return bool
   */
  public function is_component(string $rep){
    //$rep = $this->repositories($rep);
    $rep = $this->repository($rep);
    if ( $rep && isset($rep['tabs']) && ($rep['alias_code']  === "components") ){
      return true;
    }
    return false;
  }

  /**
   * Checks if a repository is a Component from URL
   *
   * @param string $url
   * @return bool
   */
  public function is_component_from_url(string $url){
    $ele = explode("/",$url);
    if ( is_array($ele) ){
      if ( $ele[2] === 'components' ){
        return true;
      }
    }
    return false;
  }

   /**
    * Checks if is a Lib from URL
    *
    * @param string $url
    * @return bool
    */
  public function is_lib_from_url(string $url){
    $ele = explode("/",$url);
    if ( is_array($ele) ){
      if ( $ele[2] === 'lib' ){
        return true;
      }
    }
    return false;
  }

   /**
    * Checks if is a Cli from URL
    *
    * @param string $url
    * @return bool
    */
  public function is_cli_from_url(string $url){
    $ele = explode("/",$url);
    if ( is_array($ele) ){
      if ( $ele[2] === 'cli' ){
        return true;
      }
    }
    return false;
  }

  /**
   * Function that returns the list of tab that contains a file or not for mvc and component
   *
   * @param string $type type project of check
   * @param string $path path project
   * @return bool||array list file with property extension and value the path of the file existing or not
   */
  public function list_tabs_with_file(string $type, string $path, string $repository){

    $list = [];
    $root = $this->get_root_path($repository);
    if ( $type === 'mvc' ){
      if ( is_string($path) ){
        if ( strpos($path, 'mvc/') === 0 ){
          $path = substr($path, 4);
        }
        if ( strpos($path, '/mvc') === 0 ){
          $path = substr($path, 5);
        }
      }
    }

    $tabs = $this->tabs_of_type_project($type);

    if ( is_string($path) && is_array($tabs) ){
      foreach($tabs as $tab){
        $exist= false;
        if ( $type === 'mvc' ){
          $file =  $root.'mvc/'.$tab['path'].$path.'.';
        }
        elseif ( $type === 'components' ){
          $file = $root. $path.'.';
        }
        foreach($tab['extensions'] as $ext ){
          if ( $this->fs->exists($file.$ext['ext']) ){
            $exist= true;
            break;
          }
        }
        if ( ($exist === false) && !in_array($tab['url'], $list) ){
          $list[] = $tab['url'];
        }
      }
      return $list;
    }
    return false;
  }

  /**
   * Returns true if the error function has been called.
   *
   * @return bool
   */
  public function has_error(){
    return !empty($this->last_error);
  }

  /**
   * Returns last recorded error, and null if none.
   *
   * @return mixed last recorded error, and null if none
   */
  public function get_last_error(){
    return $this->last_error;
  }

  /************************** REPOSITORIES **************************/

  /**
   * Makes the repositories' configurations.
   *
   * @param string $code The repository's name (code)
   * @return array|bool
   */
  public function get_repositories(string $project_name =''){
    return $this->projects->get_repositories($project_name);
  }

  /**
   * Gets a repository's configuration.
   *
   * @param string $code The repository's name (code)
   * @return array|bool
   */
  public function repository($name){
    return $this->projects->repository($name);
  }

  /**
   * Returns the repository object basing on the given id
   *
   * @param string $id
   * @return void
   */
  public function repository_by_id(string $id){
    return $this->projects->repository_by_id($id);
  }

  /**
   * Returns the repository's name or object from an URL.
   *
   * @param string $url
   * @param bool $obj
   * @return bool|int|string
   */
  public function repository_from_url(string $url, bool $obj = false){
    //search repository
    return $this->projects->repository_from_url($url, $obj);
  }

  /**
   * Checks if a repository is a MVC
   *
   * @param string $rep
   * @return bool
   */
  public function is_MVC(array $rep){
    if ( isset($rep['tabs']) &&
       ($rep['alias_code'] === 'mvc')
    ){
      return true;
    }
    return false;
  }

  /**
   * Checks if a repository is a MVC from URL
   *
   * @param string $url
   * @return bool
   */
  public function is_MVC_from_url(string $url){
    $ele = explode("/",$url);
    if ( is_array($ele) ){
      if ( $ele[2] === 'mvc' ){
        return true;
      }
    }
    return false;
  }


  /**
   * Replaces the constant at the first part of the path with its value.
   *
   * @param string $st
   * @return bool|string
   */
  public function decipher_path($st){
    //return $this->projects::decipher_path($st);
    $st = \bbn\str::parse_path($st);
    //get root absolute of the file
    foreach( $this->repositories as $i => $rep){
      if ( strpos($st, $rep['name']) === 0 ){
        $root = $rep['root_path'];//$this->get_root_path($i);
        $bit_rep = explode('/', $i);
        break;
      }
    }
    //the root of the file is removed
    if ( !empty($root) && !empty($bit_rep) ){
      $bits = explode('/', $st);
      $part_bits = array_diff($bits, $bit_rep);
      array_shift($part_bits);
      /** @var string $path The path that will be returned */
      $path = $root.'/'.implode('/', $part_bits);
      return  \bbn\str::parse_path($path);
    }
    return false;
  }

  public function get_app_path(){
    return $this->projects->get_app_path();
  }

  public function get_lib_path(){
    return $this->projects->get_lib_path();
  }

  public function get_data_path(string $plugin = ''){
    if ( $this->project !== 'apst-app' ){
      if ( (strlen($plugin) > 0) &&
        empty(array_search(substr($plugin, strlen('appui-')),array_keys($this->routes)))
      ){
        return false;
      }
    }
    return $this->projects->get_data_path($plugin);
  }

  public function get_name_project(){
    return $this->project;
  }

  /**
   * Gets the real root path from a repository's id as recorded in the options.
   *
   * @param string|array $repository The repository's name (code) or the repository's configuration
   * @return bool|string
   */
  public function get_root_path($rep){
    return $this->projects->get_root_path($rep);
  }
  /************************ END REPOSITORIES ************************/

  /************************** ACTIONS **************************/

  /**
   * (Load)s a file.
   *
   * @param string $url File's URL
   * @return array|bool
   */
  public function load(string $url){

    $real = $this->url_to_real($url, true);

    if ( is_array($real) &&
      !empty($real['file']) &&
      !empty($real['mode']) &&
      !empty($real['repository'])
    ){

      $this->set_current_file($real['file']);
      $f = [
        'mode' => $real['mode'],
        'tab' => $real['tab'],
        'ssctrl' => $real['ssctrl'] ?? 0,
        'extension' => \bbn\str::file_ext(self::$current_file),
        'permissions' => false,
        'selections' => false,
        'line' => false,
        'char' => false,
        'marks' => false,
        'repository' => $real['repository']['code'],
        //'file' => self::$current_file
      ];

      if ( $this->fs->is_file(self::$current_file) ){
       
        $f['value'] = $this->fs->get_contents(self::$current_file);

        $root = $this->get_root_path($real['repository']['name']);

        $file = substr($real['file'], strlen($root));
        $file_name = \bbn\str::file_ext($real['file'],1)[0];

        $file_path = substr($url,  strlen($real['repository']['name'])+1);

        $file_path = substr($file_path, 0, strpos($file_path,$file_name)-1);

        $val = [
          'repository' => $real['repository'],
          'filePath' => dirname($file),
          'ssctrl' => $real['ssctrl'] ?? 0,
          'filename' => $file_name,
          'component_vue' => $this->is_component_from_url($url),
          'extension'=> \bbn\str::file_ext($real['file'],1)[1],
          'full_path' => \bbn\str::parse_path($real['repository']['path'].'/'.$file),
          'path' => $file_path, // substr($file_path,  strlen($real['repository']['path'])+1),
          'tab' => $real['tab']
        ];

        if ( $preferences = $this->get_file_preferences($val) ){
          $f = array_merge($f, $preferences);
        }
        if ( ($permissions = $this->get_file_permissions()) && (($this->project === 'apst-app') || (constant('BBN_PROJECT_NAME') === 'apst-app')) ){
          $f = array_merge($f, $permissions);
          /*if ( $id_opt = $this->option_id() ){
            $val_opt = $this->options->option($id_opt);
          }*/
          /*if( !empty($val_opt) ){
            foreach ( $f as $n => $v ){
              if ( isset($val_opt[$n]) ){
                $f[$n] = $val_opt[$n];
              }
            }
          }*/
        }

      }
      elseif ( !empty($real['tab']) &&
       ( ($i = \bbn\x::find($real['repository']['tabs'], ['url' => $real['tab']])) !== false )
      ){
        if( !empty($real['repository']['tabs'][$i]['extensions'][0]['default']) ){
          $f['value'] = $real['repository']['tabs'][$i]['extensions'][0]['default'];
        }
      }
      elseif (!empty($real['repository']['extensions'][0]['default'])){
        $f['value'] = $real['repository']['extensions'][0]['default'];
      }
      else {
        $f['value'] = '';
      }
      $f['id'] = self::$current_id;
      return $f;
    }
    return false;
  }

  /**
   * Saves a file.
   *
   * @param array $file
   * @return array|string
   */
  public function save(array $file){
    if ( $this->set_current_file($this->decipher_path($file['full_path'])) ){
      /*if ( $this->get_origin() !== 'appui-ide' ){
        die(var_dump(self::$current_file, self::$current_id));
      }*/

      // Delete the file if code is empty and if it isn't a super controller
      if ( empty($file['code']) && ($file['tab'] !== '_ctrl') ){

        if ( @unlink(self::$current_file) ){
          //temporaney
          if ( $this->get_origin() !== 'appui-ide' ){
            // Remove permissions
            $this->delete_perm();
          }
          if ( !empty(self::$current_id) ){
            // Remove ide backups ad file preference
            $this->backup_history( $file ,'delete');
          }
          return ['deleted' => true];
        }
      }
      //in case of file create or modify history and if exists file prference modify
      if ( $this->fs->is_file(self::$current_file) ){
        $this->backup_history( $file ,'create');
        $this->backup_preference_files($file, $file['state'], 'change');
      }
      elseif ( !$this->fs->is_dir(dirname(self::$current_file)) ){
        $this->fs->create_path(dirname(self::$current_file));
      }

      if ( !empty($file['tab']) && ($file['tab'] === 'php') && !$this->fs->is_file(self::$current_file) ){
        if ( !$this->create_perm_by_real($file['full_path']) ){
          return $this->error(_("Impossible to create the option"));
        }
      }
   
      if ( !file_put_contents(self::$current_file, $file['code']) ){
        return $this->error(_('Error: Save'));
      };
      return ['success' => true];
    }
    return $this->error(_('Error: Save'));
  }

  /**
   * Creates a new file|directory
   *
   * @param array $cfg
   * @return bool
   */
  public function create(array $cfg){
    if ( !empty($cfg['repository']) &&
      !empty($cfg['repository']['path']) &&
      !empty($cfg['name']) &&
      !empty($cfg['path']) &&
      isset($cfg['is_file'], $cfg['extension'], $cfg['tab'], $cfg['tab_path'], $cfg['type'])
    ){

      $path = $this->get_root_path($cfg['repository']['name']);

      if ( ($cfg['repository']['alias_code'] === 'bbn-project') && !empty($cfg['type']) ){
        if ( $cfg['type'] === 'components' ){
          $path .= $cfg['path'].$cfg['name'];
        }
        if ( $cfg['type'] === 'mvc' ){
          if ( $cfg['path'] === 'mvc/' ){
            $path .= 'mvc/'.$cfg['tab_path'];
          }
          else{
            $path .= 'mvc/'.$cfg['tab_path'].$cfg['path'];
          }
        }
        if ( ($cfg['type'] === 'lib') || ($cfg['type'] === 'cli') ){
          $path .= $cfg['path'];
        }
      }
      else {
        if ( !empty($cfg['tab_path']) ){
          $path .= $cfg['tab_path'];
        }
      }

      if ( ($cfg['path'] !== './') && empty($cfg['type']) ){
        $path .= $cfg['path'];
      }


      // New folder
      if ( empty($cfg['is_file']) ){
        if ( $this->fs->is_dir($path.$cfg['name']) ){
          $this->error(_("Directory exists"));
          return false;
        }
        if ( (($cfg['repository']['alias_code'] !== 'bbn-project')) ||
          (($cfg['repository']['alias_code'] === 'bbn-project') && !empty($cfg['type'])) &&
           ($cfg['type'] !== 'components')
        ){
          $path .= $cfg['name'];
        }
        if ( empty($this->fs->create_path($path)) ){
          $this->error(_("Impossible to create the directory"));
          return false;
        }
        return true;
      }
      // New file
      elseif ( !empty($cfg['is_file']) && !empty($cfg['extension']) ){
        $file = $path .'/'. $cfg['name'] . '.' . $cfg['extension'];
        $file = str_replace('//','/', $file);
        if ( !$this->fs->is_dir($path) && empty($this->fs->create_path($path)) ){
          $this->error(_("Impossible to create the container directory"));
          return false;
        }

        if ( $this->fs->is_dir($path) ){
          if ( $this->fs->is_file($file) ){
            $this->error(_("File exists"));
            return false;
          }
          if ( !file_put_contents($file, $cfg['default_text']) ){
            $this->error(_("Impossible to create the file"));
            return false;
          }
        }
        // Add item to options table for permissions
        if ( (empty($cfg['type']) || ($cfg['type'] !== 'components')) &&
          !empty($cfg['tab']) && ($cfg['tab_url'] === 'php') && !empty($file)
        ){
          if ( !$this->create_perm_by_real($file) ){
            return $this->error(_("Impossible to create the option"));
          }
        }
        return true;
      }
    }
    return false;
  }

  /**
   * Copies a file or a folder.
   *
   * @param $cfg
   * @return bool
   */
  public function copy(array $cfg){
    return $this->operations($cfg, 'copy');
  }

  /**
   * Renames a file or a folder.
   *
   * @param $cfg
   * @return bool
   */
  public function rename(array $cfg){
    return $this->operations($cfg, 'rename');
  }

  /**
   * Moves a file or a folder.
   *
   * @param $cfg
   * @return bool
   */
  public function move(array $cfg){
    return $this->operations($cfg, 'move');
  }

  /**
   * Renames a file or a folder.
   *
   * @param $cfg
   * @return bool
   */
  public function delete(array $cfg){
    return $this->operations($cfg, 'delete');
  }

  /********************** END ACTIONS **************************/

  /************************** PERMISSIONS **************************/

  /**
   * Gets file's permissions
   *
   * @param string $file The file's path
   * @return array|false
   */
  public function get_file_permissions(string $file = null){
    if ( empty($file) ){
      $file = self::$current_file;
    }
    if ( !empty($file) &&
      ($id_opt = $this->real_to_perm($file)) &&
      ($opt = $this->options->option($id_opt))
    ){
      $ret = [
        'permissions' => [
          'id' => $opt['id'],
          'code' => $opt['code'],
          'text' => $opt['text'],
          'children' => []
        ]
      ];
      if ( isset($opt['help']) ){
        $ret['permissions']['help'] = $opt['help'];
      }
      $sopt = $this->options->full_options($opt['id']);
      foreach ( $sopt as $so ){
        array_push($ret['permissions']['children'], [
          'code' => $so['code'],
          'text' => $so['text']
        ]);
      }
      return $ret;
    }
    return false;
  }

  /**
   * Creates a permission option from a real file/dir's path
   *
   * @param string $file The real file/dir's path
   * @param string $type The type of real (file/dir)
   * @return bool
   */
  public function create_perm_by_real(string $file, string $type = 'file'): bool
  {
    if ( !empty($file) &&
      $this->fs->is_dir($this->get_app_path()) &&
      // It must be a controller
      (strpos($file, '/mvc/public/') !== false)
    ){
      $is_file = $type === 'file';
      // Check if it's an external route
      foreach ( $this->routes as $r ){
        if ( strpos($file, $r['path']) === 0 ){
          // Remove route
          $f = substr($file, \strpos($file, 'src/mvc/public'));
          // Remove /mvc/public
          $f = substr($f, \strlen('src/mvc/public'));
          // Add the route's name to path
          $f = $r['url'] . '/' . $f;
          break;
        }
      }
    
      // Internal route
      if ( empty($f) ){
        $root_path = $this->get_app_path().'mvc/public/';
        if ( strpos($file, $root_path) === 0 ){
          // Remove root path
          $f = substr($file, \strlen($root_path), \strlen($file));
        }
      }
      if ( !empty($f) ){
        $bits = \bbn\x::remove_empty(explode('/', $f));

        $code = $is_file ? \bbn\str::file_ext(array_pop($bits), 1)[0] : array_pop($bits).'/';
        $id_parent = $this->options->from_code(self::BBN_PAGE, self::BBN_PERMISSIONS, self::BBN_APPUI);

        foreach ( $bits as $b ){
          if ( !$this->options->from_code($b.'/', $id_parent) ){
            $this->options->add([
              'id_parent' => $id_parent,
              'code' => $b.'/',
              'text' => $b
            ]);
          }
          $id_parent = $this->options->from_code($b.'/', $id_parent);
        }
        if ( !$this->options->from_code($code, $id_parent) ){
          $this->options->add([
            'id_parent' => $id_parent,
            'code' => $code,
            'text' => $code
          ]);
        }

        if( !empty($code)  && !empty($opt = $this->options->from_code($code, $id_parent)) ){
          return $opt;
        }
      }
      elseif ( !$is_file ){
        return $this->options->from_code(self::BBN_PAGE, self::BBN_PERMISSIONS, self::BBN_APPUI);
      }
      return true;
    }
    return false;
  }

  /**
   * Deletes permission from a real file's path
   *
   * @param string $file The real file's path
   * @return bool
   */
  public function delete_perm($file = null): bool
  {
    if ( empty($file) ){
      $file = self::$current_file;
    }
    if ( !empty($file) && ($id_opt = $this->real_to_perm($file)) && $this->options->remove($id_opt) ){
      return true;
    }
    return false;
  }

  /**
   * Changes permissions to a file/dir from the old and new real file/dir's path
   *
   * @param string $old The old file/dir's path
   * @param string $new The new file/dir's path
   * @param string $type The type (file/dir)
   * @return bool
   */
  public function change_perm_by_real(string $old, string $new, string $type = 'file'):  bool
  {
    $type = strtolower($type);
    if ( !empty($old) &&
      !empty($new) &&
      !empty($this->fs->exists($new)) &&
      ($id_opt = $this->real_to_perm($old, $type)) &&
      !$this->real_to_perm($new, $type)
    ){

      $is_file = $type === 'file';
      $code = $is_file ? \bbn\str::file_ext(basename($new), 1)[0] : basename($new).'/';
      if ( $id_parent = $this->create_perm_by_real(dirname($new).'/', 'dir') ){
        $this->options->set_code($id_opt, $code);
        $this->options->move($id_opt, $id_parent);
        return true;
      }
    }
    return false;
  }

  /**
   * Moves permissions to a file/dir from the old and new real file/dir's path
   *
   * @param string $old The old file/dir's path
   * @param string $new The new file/dir's path
   * @param string $type The type (file/dir)
   * @return bool
   */
  public function move_perm_by_real(string $old, string $new, string $type = 'file'): bool
  {
    $type = strtolower($type);
    if ( !empty($old) &&
      !empty($new) &&
      !empty($this->fs->exists($new))
    ){
      $id_opt = $this->real_to_perm($old, $type);
      $id_new_opt = $this->real_to_perm($new, $type);
      if ( empty($id_new_opt) ){
        $id_new_opt = $this->create_perm_by_real(dirname($new).'/', 'dir');
      }
      if ( ($id_opt !== $id_new_opt) && !empty($id_new_opt) ){
        $is_file = $type === 'file';
        $code = $is_file ? \bbn\str::file_ext(basename($new), 1)[0] : basename($new).'/';
        if ( $id_parent = $this->create_perm_by_real(dirname($new).'/', 'dir') ){
          $this->options->set_code($id_opt, $code);
          $this->options->move($id_opt, $id_parent);
          return true;
        }
      }
    }
    return false;
  }

  /**
   * Returns the permission's id from a real file/dir's path
   *
   * @param string $file The real file/dir's path
   * @param string $type The path type (file or dir)
   * @return bool|int
   */
  public function real_to_perm(string $file, $type = 'file'){
    if ( empty($file) ){
      $file = self::$current_file;
    }
    if ( !empty($file) &&
      $this->fs->is_dir($this->get_app_path()) &&
      // It must be a controller
      (strpos($file, '/mvc/public/') !== false)
    ){
      $is_file = $type === 'file';
      // Check if it's an external route
      foreach ( $this->routes as  $r ){
        if ( strpos($file, $r['path']) === 0 ){
          // Remove route
          $f = substr($file, \strlen($r['path']), \strlen($file));
          // Remove /mvc/public
          $f = substr($f, \strlen('src/mvc/public'), \strlen($f));
          // Add the route's name to path
          $f = $r['url'] . '/' . $f;
          break;
        }
      }
      // Internal route
      if ( empty($f) ){
        $root_path = $this->get_app_path().'mvc/public/';
        if ( strpos($file, $root_path) === 0 ){
          // Remove root path
          $f = substr($file, \strlen($root_path), \strlen($file));
        }
      }
      if ( !empty($f) ){
        $bits = \bbn\x::remove_empty(explode('/', $f));
        $code = $is_file ? \bbn\str::file_ext(array_pop($bits), 1)[0] : array_pop($bits).'/';
        $bits = array_map(function($b){
          return $b.'/';
        }, array_reverse($bits));
        array_unshift($bits, $code);
        array_push($bits, $this->_permissions());
        return $this->options->from_code($bits);
      }
    }
    return false;
  }

  /********************** END PERMISSIONS **************************/

  /********************** PREFERENCES **************************/

  /**
   * Gets file's preferences
   *
   * @param string $cfg info for get file json
   * @return array|null
   */
  public function get_file_preferences(array $cfg = []): ?array
  {
    if ( !empty($cfg) ){
      if ( !empty($backup = $this->get_path_backup($cfg)) && !empty($backup['path_preference']) &&
        $this->fs->exists($backup['path_preference'].$cfg['filename'].'.json')
      ){
        $pref = json_decode($this->fs->get_contents($backup['path_preference'].$cfg['filename'].'.json'), true);
        if ( !empty($pref) ){
          return [
            'selections' => $pref['selections'] ?: [],
            'marks' => isset($pref['marks']) ? $pref['marks'] : [],
            'line' => (int)$pref['line'] ?: 0,
            'char' => (int)$pref['char'] ?: 0,
          ];
        }
      }
    }
    return null;
  }

  /**
   * Get theme current of the project
   *
   * @return string
   */
  public function get_theme(){
    $opt_theme = $this->options->from_code(self::THEME, self::IDE_PATH, self::BBN_APPUI);
    $pref_arch = $this->pref->get_class_cfg();
    
    $pref = $this->db->select_one( $pref_arch['tables']['user_options'],  $pref_arch['arch']['user_options']['id'], [
      $pref_arch['arch']['user_options']['id_user'] => $this->pref->get_user(),
      $pref_arch['arch']['user_options']['id_option'] => $this->projects->get_id()
    ]);
    //if there is no preference, the theme value will take it from the option
    if ( !empty($pref) ){
      $val =  $this->db->select_one( $pref_arch['tables']['user_options_bits'],  'cfg', [
        $pref_arch['arch']['user_options_bits']['id_user_option'] => $pref,
        $pref_arch['arch']['user_options_bits']['id_option'] => $opt_theme,
      ]);
      $val = json_decode($val, true);
      if ( isset($val['theme']) ){
        return $val['theme'];
      }
    }
    return '';
  }

 /**
  * Function for set preference theme for every single project
  *
  * @param string $theme
  * @return string
  */
  public function set_theme(string $theme = ''){
    $opt_theme = $this->options->from_code(self::THEME, self::IDE_PATH, self::BBN_APPUI);
    $pref_arch = $this->pref->get_class_cfg();

    if ( !empty($opt_theme) ){
      //id_option is the project
      $pref = $this->db->select_one( $pref_arch['tables']['user_options'],  $pref_arch['arch']['user_options']['id'], [
        $pref_arch['arch']['user_options']['id_user'] => $this->pref->get_user(),
        $pref_arch['arch']['user_options']['id_option'] => $this->projects->get_id()
      ]);
      //if it does not exist, the preference for user and project is created
      if ( empty($pref) ){
        $pref  = $this->pref->add($this->projects->get_id(), []);
      }

      if ( !empty($pref) ){
        $id_bit = $this->db->select_one( $pref_arch['tables']['user_options_bits'],  $pref_arch['arch']['user_options_bits']['id'], [
          $pref_arch['arch']['user_options_bits']['id_user_option'] => $pref,
          $pref_arch['arch']['user_options_bits']['id_option'] => $opt_theme
        ]);
        $cfg = [
          'id_option' => $opt_theme,
          'cfg' =>  json_encode(['theme' => $theme])
        ];

        if ( !empty($id_bit) && \bbn\str::is_uid($id_bit) ){
          if ( !empty($this->pref->update_bit($id_bit, $cfg, true)) ){
            return true;
          }
        }
        else{
          if ( !empty($this->pref->add_bit($pref, $cfg)) ){
            return true;
          }
        }
      }
    }
    return false;
  }

 /******************** END PREFERENCES ************************/

  /******************** OPENED AND RECENT FILES BIT ************************/


  /**
   * Create or update bit recent file preference
   *
   * @param string $file code option
   * @param string $id_link id option file preference
   * @return bool
   */
  public function set_recent_file(string $file): bool
  {
    $bit = false;
    $id_recent_file = $this->options->from_code(self::RECENT_FILE, self::IDE_PATH, self::BBN_APPUI);
    if ( !empty($id_recent_file) ){
      //search preference and if not exsist preference add a new
      $pref = $this->pref->get_by_option($id_recent_file);
      $id_pref = !empty($pref) ? $pref['id'] : $this->pref->add($id_recent_file, []);
    }
    if ( !empty($id_pref) ){
      //search bit in relation at user preference
      $bit_data = $this->get_bit_by_file($file, $id_pref);
    }

    $date = date('Y-m-d H:i:s');
    $cfg =[];
    //set bit
    if ( ($bit_data !== null) ){
      $info= json_decode($bit_data['cfg'], true);
      $cfg = [
        'id_option' => null,
        'text' => $file,
        'cfg' => [
          'bit_creation' => $info['bit_creation'],
          'last_date' => $date,
          'number' => $info['number'] + 1,
        ]
      ];
      if ( !empty($this->pref->update_bit($bit_data['id'], $cfg, true)) ){
        $bit = true;
      }
    }
    //add bit
    else{
      $cfg = [
        'bit_creation' => $date,
        'last_date' => $date,
        'number' => 0,
      ];
      if ( !empty($id_pref) && $this->pref->add_bit($id_pref,[
        //'id_option' => $id_link,
        'id_option' => null,
        'cfg' =>  json_encode($cfg),
        'text' => $file,
        ])
      ){
        $bit = true;
      }
    }
    return !empty($bit) && !empty($id_pref);
  }

  /**
   * Add or update option file in repository
   *
   * @return bool
   */
  public function tracking(array $file, string $file_code, array $info, bool $setRecent = true): bool
  {
    $bit = false;
    if ( ($id_option_opened = $this->options->from_code(self::OPENED_FILE,self::IDE_PATH, self::BBN_APPUI)) ){
      //search preference and if not exsist preference add a new
      $pref = $this->pref->get_by_option($id_option_opened);
      $id_pref = !empty($pref) ? $pref['id'] : $this->pref->add($id_option_opened, []);
      $pref_file = $this->backup_preference_files($file, $info, 'create');
      if ( !empty($pref_file) && !empty($id_pref) ){
        $file_path = $file['repository']['name'].'/'.$file_code;
        $bit_data = $this->get_bit_by_file($pref_file, $id_pref);
        if ( $bit_data !== null ){
          $cfg = [
            'cfg' =>[
              'last_open' => date('Y-m-d H:i:s')
            ]
          ];
          //set bit why exist
          if (!empty($this->pref->update_bit($bit_data['id'], $cfg, true)) ){
            $bit = true;
          }
        }
        //add bit why not exist
        else {
          $cfg = [
            'last_open' => date('Y-m-d H:i:s')
          ];

          if ( !empty($id_pref) && $this->pref->add_bit($id_pref,[
            'id_option' => null,
            'cfg' => json_encode($cfg),
            'text' => $file_path
          ])){
            $bit = true;
          }
        }
        if ( $setRecent ){
          return !empty($bit) && !empty($pref_file) && !empty($id_pref) && $this->set_recent_file($file_path);
        }
        else{
          return !empty($bit) && !empty($pref_file) && !empty($id_pref);
        }
      }
    }
    return $pref_file;
  }

  /**
   * return list files preferences
   *
   * @param integer $limit file numbers to be taken
   * @return null|array
   */
  public function get_recent_files( int $limit = 10 ): ?array
  {
    $perm = $this->options->from_code(self::RECENT_FILE, self::IDE_PATH, self::BBN_APPUI);
    $all = [];
    if ( !empty($perm) ){
      $pref = $this->pref->get_by_option($perm);
      if ( !empty($pref['id']) ){
        $pref_arch = $this->pref->get_class_cfg();
        $recents =  $this->db->rselect_all([
          'table' => $pref_arch['tables']['user_options_bits'],
          'fields' => [
            $pref_arch['arch']['user_options_bits']['id'],
            $pref_arch['arch']['user_options_bits']['id_user_option'],
            $pref_arch['arch']['user_options_bits']['id_option'],
            $pref_arch['arch']['user_options_bits']['cfg'],
            $pref_arch['arch']['user_options_bits']['text'],
            'date' => 'bbn_users_options_bits.cfg->"$.last_date"',
            'num' => 'bbn_users_options_bits.cfg->"$.number"'
          ],
          'where' => [
            'conditions' => [[
              'field' => $pref_arch['arch']['user_options_bits']['id_user_option'],
              'value' => $pref['id']
            ]]
          ],
          'limit' => 10,
          'order' =>['date' => "DESC"]
        ]);
        //configure path for link  for each recent file
        foreach ( $recents as $id => $bit ){
          //path for link
          $arr = explode("/",$bit['text']);
          $type = '';
          $root = $arr[0].'/'.$arr[1];
          if ( !empty($arr[2]) ){
            $type = $arr[2];
            unset($arr[2]);
          }
          unset($arr[0]);
          unset($arr[1]);
          if ( ($type !== 'mvc') && ($type !== 'components') ){
            $tab = 'code';
          }
          else{

            $tab = array_shift($arr);
            $tab = $tab=== 'public' ? 'php' : $tab;
          }

          $arr = implode('/', $arr);
          $file = explode('.', $arr)[0];
          $path = \bbn\str::parse_path('file/'.$root.'/'.$type.'/'.$file.'/_end_/'.$tab);

          $value = json_decode($bit['cfg'], true);
          $all[] = [
            'cfg' => !empty($value['file_json']) ? json_decode($this->fs->get_contents(self::$backup_path.$value['file_json']), true) : [],
            'file' =>  \bbn\str::parse_path($bit['text']),
            'repository' =>  $root,
            'path' => $path,
            'type' => $type === '' ? false : $type
          ];
        }
      }
    }
    return !empty($all) ? $all : null;
  }

  /******************** END OPENED AND RECENT FILES ************************/



  /*************************** FILE ***************************/

  /**
   * Returns the file's URL from the real file's path.
   *
   * @param string $file The real file's path
   * @return bool|string
   */
  public function real_to_url(string $file){
    return $this->projects->real_to_url($file);
   //get root for path
    //foreach ( $this->repositories() as $i => $d ){
   /* foreach ( $this->repositories as $i => $d ){
      $root = $d['root_path'];
      if (
        $root &&
        (strpos($file, $root) === 0)
      ){
        $rep = $i;
        break;
      }
    }
    if ( isset($rep) ){
      $res = $rep.'/src/';
      $bits = explode('/', substr($file, \strlen($root)));

      // MVC
      if ( !empty($d['tabs']) ){
        $tab_path = array_shift($bits);
        $fn = array_pop($bits);
        $ext = \bbn\str::file_ext($fn);
        $fn = \bbn\str::file_ext($fn, 1)[0];
        $res .= implode('/', $bits);
        foreach ( $d['tabs'] as $k => $t ){
          if (
            empty($t['fixed']) &&
            ($t['path'] === $tab_path . '/')
          ){
            $res .= "/$fn/$t[url]";
            break;
          }
        }
      }
      // Normal file
      else {
        $res .= implode('/', $bits);
      }
      return \bbn\str::parse_path($res);
    }
    return false;*/
  }


  /**
   * check if $path is of a plugin
   *
   * @param string $path
   * @return bool
   */
  public function is_plugin($path){
    $plugin = false;
    if ( is_array($this->routes) ){
      foreach( $this->routes as $route ){
        if ( $path === $route['path'].'src/'){
          $plugin = true;
          break;
        }
      }
    }
    return $plugin;
  }

  /**
   * Gets the real file's path from an URL
   *
   * @param string $url The file's URL
   * @param bool $obj
   * @return bool|string|array
   */
  public function url_to_real(string $url, bool $obj = false){
     //get reposiotry of the url
    if ( ($rep = $this->repository_from_url($url, true)) &&
      ($res = $this->get_root_path($rep['name']))
    ){
      $plugin = $this->is_plugin($res);
      //for analyze url for get tab , type etc..
      $bits = explode('/', substr($url, \strlen($rep['name'])+1));
      //if is project get tabs or if is components or is mvc
      if ( $rep['alias_code'] === 'bbn-project' ){
        if ( !empty($this->is_component_from_url($url)) &&
          !empty($ptype = $this->get_type('components'))
        ){
          $rep['tabs'] = $ptype['tabs'];
        }
        if ( !empty($this->is_MVC_from_url($url)) &&
          !empty($ptype = $this->get_type('mvc'))
        ){
          $rep['tabs'] = $ptype['tabs'];
        }
      }

      $o = [
        'mode' => false,
        'repository' => $rep,
        'tab' => false
      ];
      $position_end = $bits[count($bits)-2] === '_end_' ? count($bits)-2 : false;
      if ( !empty($bits) && $position_end ){
        // Tab's nane
        //case component or mvc
        if ( !empty($rep['tabs']) && (end($bits) !== 'code') ){
          // Tab's nane
          $tab = $bits[$position_end+1];
          unset($bits[$position_end+1]);
          // File's name
          $file_name = $bits[$position_end-1];
          unset($bits[$position_end-1]);

          unset($bits[$position_end]);
          array_shift($bits);
          // File's path
          $file_path = implode('/', $bits);
          // Check if the file is a superior super-controller
          $ssc = $this->superior_sctrl($tab, $file_path);
          $tab = $ssc['tab'];
          $o['tab'] = $tab;
          $file_path = $ssc['path'].'/';
          if ( ($i = \bbn\x::find($rep['tabs'], ['url' => $tab])) !== false ){
            $tab = $rep['tabs'][$i];
            if( !empty($this->is_MVC_from_url($url)) ){
              $res .= 'mvc/';
            }
            if( empty($this->is_component_from_url($url)) ){
              $res .= $tab['path'];
            }
            elseif ( !empty($this->is_component_from_url($url)) ){
              $res .= 'components/';
            }
            if ( !empty($tab['fixed']) ){
              $res .= $file_path . $tab['fixed'];
              $o['mode'] = $tab['extensions'][0]['mode'];
              $o['ssctrl'] = $ssc['ssctrl'];
            }
            else {
              $res .=  $file_path . $file_name;
              $ext_ok = false;
              foreach ( $tab['extensions'] as $e ){
                if ( $this->fs->is_file("$res.$e[ext]") ){
                  $res .= ".$e[ext]";
                  $ext_ok = true;
                  $o['mode'] = $e['mode'];
                  break;
                }
              }
              if ( empty($ext_ok) ){
                $res .= '.' . $tab['extensions'][0]['ext'];
                $o['mode'] = $tab['extensions'][0]['mode'];
              }
            }
          }
          /*else {
            return false;
          }*/
        }
        else {
          unset($bits[$position_end+1]);
          // File's name
          $file_name = $bits[$position_end-1];
          unset($bits[$position_end]);
          $res .= '/'.implode('/', $bits);
          if( is_array($rep) ){
            //temporaney for lib plugin
            if ( !empty($rep['extensions'] ) ){
              foreach ( $rep['extensions'] as $ext ){
                if ( $this->fs->is_file("$res.$ext[ext]") ){
                  $res .= ".$ext[ext]";
                  $o['mode'] = $ext['mode'];
                }
              }
            }
            else{
              if ( $this->fs->is_file($res.'.php') ){
                $res .= ".php";
                $o['mode'] = 'php';
              }
            }
          }
          if ( empty($o['mode']) && !empty($rep['extensions']) ){
            $res .= '.' . $rep['extensions'][0]['ext'];
            $o['mode'] = $rep['extensions'][0]['mode'];
          }
        }
        $res = \bbn\str::parse_path($res);
        if ( $obj ){
          $o['file'] = $res;
          return $o;
        }
        return $res;
      }
    }
    return false;
  }

  /**
   * Returns the file's ID from the real file's path.
   *
   * @param string $file The real file's path
   * @return bool|string
   */
 /* public function real_to_id($file){
    if ( ($rep = $this->repository_from_url($this->real_to_url($file), true)) && \defined($rep['bbn_path']) ){
      $bbn_p = $rep['bbn_path'] === 'BBN_APP_PATH' ? \bbn\mvc::get_app_path() : constant($rep['bbn_path']);
      if ( strpos($file, $bbn_p) === 0 ){
        $f = substr($file, \strlen($bbn_p));
        return \bbn\str::parse_path($rep['bbn_path'].'/'.$f);
      }
    }
    return false;
  }*/

  /***
   * Returns the filename and relative path from an URL
   *
   * @param string $url
   * @return bool|string
   */
  /*public function file_from_url(string $url){
    $rep = $this->repository_from_url($url);
    if ( $this->is_MVC($rep) ){
      $last = basename($url);
      if ( $repo = $this->repository($rep) ){
      $path = $this->get_root_path($rep).substr($url, \strlen($rep));
        $tabs = $repo['tabs'];

        foreach ( $tabs as $key => $r ){
          if ( $key === $last ){
            foreach ( $tabs as $key2 => $r2 ){
              foreach ( $r2['extensions'] as $ext ){
                if ( is_file($path.'.'.$ext['ext']) ){
                  goto endFunc;
                }
              }
            }
            $url = dirname($url);
            break;
          }
        }
      }
    }
    endFunc:
    return substr($url, \strlen($rep));
  }*/

  /************************* END FILE *************************/

  /**
   * Returns all backups of a file.
   *
   * @param string $url The file's URL
   * @param bool $all Tparameter that allows you to have all the code if it is set to true
   * @return array|bool
   */
  public function history(string $url, array $repository = [], bool $all = false){
    $check_ctrl = false;
    $copy_url = explode("/", $url);
    $backups = [];
    $history_ctrl = [];
    if ( !empty($repository) && !empty($repository['name']) ){
      $path = self::$backup_path . $repository['root'].'/'.substr($url, strpos($url,$repository['code'],1));
    }
    else{
      // File's backup path
      $path = self::$backup_path . $url;
    }
    if ( !empty($url) && !empty(self::$backup_path) ){
      $ctrl_path = explode("/", $path);
      for($y=0; $y <2; $y++){
        array_pop($ctrl_path);
      }
      //check if there is "_ctrl" in the url as the last step of the "$url"; in that case we tart $url to give the right path to get it to take its own backup files.
      if ( end($copy_url) === "_ctrl" ){
        $url = explode("/", $url);
        array_pop($url);
        $url = implode("/", $url);
        $check_ctrl_files = true;
        $copy_url = explode("/", $url);
        for($y=0; $y <2; $y++){
          array_pop($copy_url);
        }
        $copy_url = implode("/", $copy_url)."/"."_ctrl";
      }

      //First, check the presence of _ctrl backups.
      $ctrl_path = implode("/", $ctrl_path)."/"."_ctrl";
      // read _ctrl if exsist
       if ( $this->fs->is_dir($ctrl_path) ){
           //If there is a "_ctrl" backup, insert it into the array that will be merged with the remaining backup at the end of the function.
          if ( $files_ctrl = $this->fs->get_files($ctrl_path) ){
             $mode = basename($ctrl_path);

             $history_ctrl = [
               'text' => basename($ctrl_path),
               'icon' => 'folder-icon',
               'folder' => true,
               'items' => [],
               'num_items' => \count($this->fs->get_files($ctrl_path))
               //'num_items' => \count(\bbn\file\dir::get_files($files_ctrl))
             ];

             //If we are requesting all files with their contents, this block returns to the "_ctrl" block.
             if ( $all === true ){

             foreach ( $files_ctrl as $file ){
               $filename = \bbn\str::file_ext($file, true)[0];
               $file_name = $filename;
               $moment = strtotime(str_replace('_', ' ', $filename));
               $date = date('d/m/Y', $moment);
               $time = date('H:i:s', $moment);

               if ( ($i = \bbn\x::find($history_ctrl['items'], ['text' => $date])) === false ){
                 array_push($history_ctrl['items'], [
                   'text' => $date,
                   'items'=> [],
                   'folder' => true,
                   'icon' => 'folder-icon'
                 ]);

                 $i = \count($history_ctrl['items']) - 1;
                 if ( ($idx = \bbn\x::find($history_ctrl['items'][$i]['items'], ['text' => $time])) === false ){
                   array_push($history_ctrl['items'][$i]['items'], [
                     'text' => $time,
                     'mode' => basename($ctrl_path),
                     'file' => $file_name,
                     'ext' => \bbn\str::file_ext($file, true)[1],
                     'uid' => $url,
                     'folder' => false
                   ]);
                 }
               }
               else {
                 $j = \bbn\x::find($history_ctrl['items'], ['text' => $date]);
                 if ( ($idx = \bbn\x::find($history_ctrl['items'][$j]['items'], ['text' => $time])) === false ){
                   array_push($history_ctrl['items'][$j]['items'], [
                     'text' => $time,
                     'code' => $this->fs->get_contents($file),
                     'folder' => false,
                     'mode' => basename($ctrl_path),
                     'folder' => false
                   ]);
                 }
               }
             }
           }
           //otherwise pass some useful parameters to get information with other posts see block in case of "$all" to false.
           else{
             $check_ctrl = true;
           }
         }
       }
       //taken or not the backup of the "_ctrl" we move on to acquire the date of the project, if set to true then as done before, we will take into consideration all the date including the contents of the files.
      if ( $all === true ){

        //if ( is_dir($path) ){
        if ( $this->fs->is_dir($path) ){
          //if we pass a path that contains all the backups
          if ( $dirs = $this->fs->get_dirs($path) ){
            if ( !empty($dirs) ){
              $mode = basename($path) === "_ctrl" || basename($path) === "model" ? "php" : basename($path);
              foreach ( $dirs as $dir ){
                //if ( $files = \bbn\file\dir::get_files($dir) ){
                if ( $files = $this->fs->get_files($dir) ){
                  foreach ( $files as $file ){
                    $filename = \bbn\str::file_ext($file, true)[0];
                    $moment = strtotime(str_replace('_', ' ', $filename));
                    $date = date('d/m/Y', $moment);
                    $time = date('H:i:s', $moment);
                    if ( ($i = \bbn\x::find($backups, ['text' => $date])) === false ){
                      array_push($backups, [
                        'text' => $date,
                        'folder' => true,
                        'items' => [],
                        'icon' => 'folder-icon'
                      ]);
                      $i = \count($backups) - 1;
                    }
                    if ( ($idx = \bbn\x::find($backups[$i]['items'], ['title' => $d])) === false ){
                      array_push($backups[$i]['items'], [
                        'text' => $d,
                        'folder' => true,
                        'items' => [],
                        'icon' => 'folder-icon'
                      ]);
                      $idx = \count($backups[$i]['items']) - 1;
                    }
                    array_push($backups[$i]['items'][$idx]['items'], [
                      'text' => $time,
                      'mode' => $mode,
                      'code' => $this->fs->get_contents($file),
                      'folder' => false
                    ]);
                  }
                }
              }
            }
          }
          //If we pass a path that contains the specific backups of a type and is set to "$all" to true then all backups of this type will return.
          else {
            if ( $files = $this->fs->get_files($path) ){
              if ( !empty($files) ){
                $mode = basename($path) === "_ctrl" || basename($path) === "model" ? "php" : basename($path);
                foreach ( $files as $file ){

                  $filename = \bbn\str::file_ext($file, true)[0];
                  $file_name = $filename;
                  $moment = strtotime(str_replace('_', ' ', $filename));
                  $date = date('d/m/Y', $moment);
                  $time = date('H:i:s', $moment);

                  if ( ($i = \bbn\x::find($backups, ['text' => $date])) === false ){
                    array_push($backups, [
                      'text' => $date,
                      'folder' => true,
                      'items' => [],
                      'icon' => 'folder-icon'
                    ]);

                    $i = \count($backups) - 1;
                    if ( ($idx = \bbn\x::find($backups[$i]['items'], ['text' => $time])) === false ){
                      array_push($backups[$i]['items'], [
                        'text' => $time,
                        'mode' => $mode,
                        'code' => $this->fs->get_contents($file),
                        'folder' => false
                      ]);
                    }
                  } else {
                    $j = \bbn\x::find($backups, ['text' => $date]);
                    if ( ($idx = \bbn\x::find($backups[$j]['items'], ['text' => $time])) === false ){
                      array_push($backups[$j]['items'], [
                        'text' => $time,
                        'mode' => $mode,
                        'code' => $this->fs->get_contents($file),
                        'folder' => false
                      ]);
                    }
                  }
                }
              }
            }
          }
        }
      }//otherwise returns the useful information for processing and to make any subsequent postings.
      else {
        //if we want you to return all the backup information useful to process and make other posts
        $listDir = $this->fs->get_dirs($path);
        if ( !empty($listDir) && !isset($check_ctrl_files) ){

          foreach ( $listDir as $val ){
            array_push($backups, [
              'text' => basename($val),
              'icon' => 'folder-icon',
              'folder' => true,
              //'num_items' => \count(\bbn\file\dir::get_files($val))
              'num_items' => \count($this->fs->get_files($val))
            ]);
          }
          //If the _ctrl backup folder exists, then it will be added to the list.
          if( $check_ctrl === true ){
            array_push($backups, $history_ctrl);
          }
        }//If we pass a path that contains the specific backups of a type and is not set "$all" then the backup of with useful information for any other posts returns.
        else {
          //If we are requesting ctrl backup files then we give it the right path and "$check_ctrl_files" is a variable that makes us understand whether or not we ask for backup files of "_ctrl".
          if ( isset($check_ctrl_files) && ($check_ctrl_files === true) ){
            $url= $copy_url;
            $path = self::$backup_path . $url;
          }
          //if ( $files = \bbn\file\dir::get_files($path) ){
          if ( $files = $this->fs->get_files($path) ){
            if ( !empty($files) ){
              $mode = basename($path) === "_ctrl" || basename($path) === "model" ? "php" : basename($path);
              foreach ( $files as $file ){
                if ( \bbn\str::file_ext($file, true)[1] !== 'json' ){
                  $filename = \bbn\str::file_ext($file, true)[0];
                  $file_name = $filename;
                  $moment = strtotime(str_replace('_', ' ', $filename));
                  $date = date('d/m/Y', $moment);
                  $time = date('H:i:s', $moment);
  
                  if ( ($i = \bbn\x::find($backups, ['text' => $date])) === false ){
                    array_push($backups, [
                      'text' => $date,
                      'folder' => true,
                      'items' => [],
                      'icon' => 'folder-icon'
                    ]);
  
                    $i = \count($backups) - 1;
                    if ( ($idx = \bbn\x::find($backups[$i]['items'], ['text' => $time])) === false ){
                      array_push($backups[$i]['items'], [
                        'text' => $time,
                        'mode' => $mode,
                        'file' => $file_name,
                        'ext' => \bbn\str::file_ext($file, true)[1],
                        'uid' => $url,
                        'folder' => false
                      ]);
                    }
                  }
                  else {
                    $j = \bbn\x::find($backups, ['text' => $date]);
                    if ( ($idx = \bbn\x::find($backups[$j]['items'], ['text' => $time])) === false ){
                      array_push($backups[$j]['items'], [
                        'text' => $time,
                        'mode' => $mode,
                        'file' => $file_name,
                        'ext' => \bbn\str::file_ext($file, true)[1],
                        'uid' => $url,
                        'folder' => false
                      ]);
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
    //If you add the "_ctrl " backup, enter it to the rest of the date.
    if( !empty($history_ctrl) && !empty($backups) && $all === true && $check_ctrl === false  ){
      array_push($backups, $history_ctrl);
    }//if you have only the backups of the super _ctrl and no other, it has been differentiated because of different paths
    else if( !empty($history_ctrl) && empty($backups) && $check_ctrl === true  ){
      array_push($backups, $history_ctrl);
    }
    return $backups;
  }

  /**
   * Returns all data of type repository
   *
   * @param string $type name ohf type
   * @return array|bool
   */
  public function get_type(string $type){
    if ( !empty($type) ){
      return self::get_appui_option($type, self::PATH_TYPE);
    }
  }

  /**
   * Returns all data of all types repository
   *
   * @return array|bool
   */
  public function get_types(){
    //$this->options->option($this->options->from_code(self::PATH_TYPE, 'ide', 'projects', 'appui'));
    return self::get_appui_option(self::PATH_TYPE);
  }

  /**
   * Returns the tabs of type repository
   *
   * @param string $type name ohf type
   * @return array|bool
   */
  public function tabs_of_type_project(string $type){
    if ( !empty($type) && ($ptype = $this->get_type($type))){
      return !empty($ptype['tabs']) ? $ptype['tabs'] : false;
    }
  }

  public function search (array $info){
    $res['success'] = false;

    if ( !empty($info['search']) &&
        !empty($info['nameRepository']) &&
        !empty($info['repository']) &&
        isset($info['typeSearch'])
    ){
      $list = [];
      $fileData = [];
      $result = [];
      $totLines = 0;
      $tot_num_files = 0;
      $occourences = 0;
      $base =  $info['repository']['name'];
      $base_rep = $this->get_root_path($base);
      //function that defines whether the search is sensitive or non-sensitive
      $typeSearch = function($element, $code, $type){
        if ( $type === "sensitive"){
          return strpos($element, $code);
        }
        else{
          return stripos($element, $code);
        }
      };

      if ( !empty($info['isProject']) ){
        $part = $info['type'];
      }
      else{
        $part = $info['repository']['path'];
      }

      $path = $base_rep.$part;

      $all = $this->fs->get_files($path, true);
      if ( is_array($all) && count($all) ){
        foreach($all as $i => $v){
          if ( basename($v) !== "cfg"  ){
            //if folder
            if ( $this->fs->is_dir($v) ){
              //case tree
              if ( !empty($info['searchFolder']) ){
                if ( !empty($info['mvc']) || ($info['type'] === 'mvc') ){
                  $content = $v;
                }
                else{
                  $content = $path;
                }
                $content .= $info['searchFolder'];
              }
              else{
                $content = $v;
              }

              $content = $this->fs->scan($content);
              if ( is_array($content) && count($content) ){
                foreach ( $content as $j => $val ){
                  $list = [];
                  // case file into folder
                  if ( $this->fs->is_file($val) ){
                    $tot_num_files++;
                    if ( $typeSearch($this->fs->get_contents($val), $info['search'], $info['typeSearch']) !== false ){
                      $path = $base_rep.$part;
                      $path_file= $val;
                      $link = explode("/", substr($val, strlen($path)+1, strlen($val)));
                      if ( (!empty($info['isProject']) && $info['type'] === 'mvc') ||
                        !empty($info['mvc'])
                      ){
                        $tab = array_shift($link);
                        $link = implode('/', $link);
                        $link = explode('.', $link);
                        $link = array_shift($link);
                      }
                      elseif ( (!empty($info['isProject']) && ($info['type'] === 'components') ) ||
                        !empty($info['components'])
                      ){
                        $link = implode('/', $link);
                        $link = explode('.', $link);
                        $tab = array_pop($link);
                        $link = $link[0];
                      }
                      elseif (  !empty($info['isProject']) && (($info['type'] === 'lib') || ($info['type'] === 'cli')) ){
                        $link = implode('/', $link);
                        $link = explode('.', $link);
                        $tab = 'code';
                        $link = $link[0];
                      }
                      elseif( empty($info['isProject']) && empty($info['type']) ) {
                        $file = $link[count($link)-1];
                        $file = explode('.', $file);
                        $tab = array_pop($file);
                        $link[count($link)-1] = array_shift($file);
                        $link = implode('/', $link);
                      }
                      //object initialization with every single file to check the lines that contain it
                      $file = new \SplFileObject($val);
                      //cycle that reads all the lines of the file, it means until it has finished reading a file
                      while( !$file->eof() ){
                        //current line reading
                        $lineCurrent = $file->current();
                        //if we find what we are looking for in this line and that this is not '\ n' then we will take the coirispjective line number with the key function, insert it into the array and the line number
                        if ( ($typeSearch($lineCurrent, $info['search'], $info['typeSearch']) !== false) && (strpos($lineCurrent, '\n') === false) ){
                          $lineNumber = $file->key()+1;
                          $name_path = $info['repository']['path'].substr(dirname($val), strlen($base_rep));
                          $position = $typeSearch($lineCurrent, $info['search'], $info['typeSearch']);
                          $line = "<strong>".'line ' . $lineNumber . ' : '."</strong>";

                          $text = $line;
                          if ( !empty($info['mvc']) ||
                            (!empty($info['isProject']) && $info['type'] === 'mvc')
                          ){
                            if ( $tab === "public" ){
                              $tab = 'php';
                            }
                            else{
                              if ( explode("/",$path_file)[1] === "html" ){
                                $lineCurrent = htmlentities($lineCurrent);
                              }
                            }
                          }
                          $text .= str_replace($info['search'], "<strong><span class='underlineSeach'>".$info['search']."</span></strong>", $lineCurrent);
                          $file_name = basename($path_file);
                          $path = dirname($base.'/'.substr($path_file, strlen($base_rep)));
                          $occourences = $occourences + substr_count($lineCurrent, $info['search']);
                          // info for code
                          $list[] = [
                            'text' => strlen($text) > 1000 ? $line."<strong><i>"._('content too long to be shown')."</i></strong>" : $text,
                            'line' =>  $lineNumber-1,
                            'position' => $position,
                            'link' => $link,
                            'tab' =>  !empty($tab) ? $tab : false,
                            'code' => true,
                            'uid' => $path.'/'.$file_name,
                            'icon' => 'nf nf-fa-code'
                          ];
                        }
                        //next line
                        $file->next();
                      }
                    }
                  }
                  //if we find rows then we will create the tree structure with all the information
                  if ( count($list) > 0 ){
                    $totLines = $totLines + count($list);
                    if ( !empty($info['mvc']) ){
                      if ( explode("/",$path_file)[1] === "public" ){
                        $tab = 'php';
                      }
                      else{
                        $tab = explode("/",$path_file)[1];
                      }
                      $link =  explode(".",substr($path_file, strlen(explode("/",$path_file)[0].'/'.explode("/",$path_file)[1])+1))[0];
                    }
                   //info file
                    $fileData = [
                      'text' =>  $path.'/'.$file_name."&nbsp;<span class='bbn-badge bbn-s bbn-bg-lightgrey'>".count($list)."</span>",
                      'icon' => 'nf nf-fa-file_code_o',
                      'numChildren' => count($list),
                      'repository' => $info['repository']['path'],
                      'uid' => $path.$file_name,
                      'file' => basename($path_file),
                      'link' => !empty($link) ? $link : false,
                      'tab' =>  !empty($tab) ? $tab : false,
                      'items' => $list,
                    ];
                    $result[] = $fileData;

                    //die(var_dump($path.$name_path,$base_rep));
                    /*if ( !isset($result[$path.$name_path]) ){
                      //info folder
                      $result[$path.$name_path]= [
                        'text' => dirname($path.$file_name),
                        'num' => 1,
                        'numChildren' => 1,
                        'items' => [],
                        'icon' => !empty($info['component']) || ($info['type'] === 'components')  ? 'nf nf-mdi-vuejs' : 'nf nf-fa-folder'
                      ];
                      $result[$path.$name_path]['items'][] = $fileData;
                    }
                    else {
                      $ctrlFile = false;
                      //  check if the file where we found one or more search results is not reinserted
                      foreach( $result[$path.$name_path]['items'] as $key => $item ){
                        if ( $item['file'] === dirname($path_file) ){
                          $ctrlFile = true;
                        }
                      }
                      //if we do not have the file, we will insert it
                      if ( empty($ctrlFile) ){
                        $result[$path.$name_path]['items'][] = $fileData;
                        $result[$path.$name_path]['num']++;
                        $result[$path.$name_path]['numChildren']++;
                      }
                    }*/

                  }
                }
              }
            } // file not contained in the folder
            else {
              $tot_num_files++;
              $list= [];
              if ( $typeSearch($this->fs->get_contents($v), $info['search'], $info['typeSearch']) !== false ){
                $path_file = substr($v, strpos($v, $info['repository']['path']));
                $file = new \SplFileObject($v);
                while( !$file->eof() ){
                  $lineCurrent = $file->current();
                  if ( ($typeSearch($lineCurrent, $info['search'], $info['typeSearch']) !== false) &&
                    (strpos($lineCurrent, '\n') === false)
                  ){
                    $lineNumber = $file->key()+1;
                    $link =  explode(".",substr($path_file, strlen(explode("/",$path_file)[0].'/'.explode("/",$path_file)[1])+1))[0];
                    $name_path = substr(dirname($v), strpos($v, $info['repository']['path']));
                    $position = $typeSearch($lineCurrent, $info['search'], $info['typeSearch']);
                    $text = "<strong>".'line ' . $lineNumber . ' : '."</strong>";
                    $text .= str_replace($info['search'], "<strong><span class='underlineSeach'>".$info['search']."</span></strong>", $lineCurrent);
                    $occourences = $occourences + substr_count($lineCurrent, $info['search']);
                    //see
                    $path = str_replace($base, (strpos($path_file, $this->get_app_path()) === 0 ? 'app/' : 'lib/'), $path);

                    if ( !empty($info['mvc']) ){
                      if ( explode("/",$path_file)[1] === "public" ){
                        $tab = 'php';
                      }
                      else{
                        $tab = explode("/",$path_file)[1];
                      }
                      $link =  explode(".",substr($path_file, strlen(explode("/",$path_file)[0].'/'.explode("/",$path_file)[1])+1))[0];
                    }
                    // info for file
                    $list[] = [
                      'text' => strlen($text) > 1000 ?  $line."<strong><i>"._('content too long to be shown')."</i></strong>" : $text,
                      'line' =>  $lineNumber-1,
                      'position' => $position,
                      'code' => true,
                      'uid' =>  $path.'/'.$file_name,
                      'icon' => 'nf nf-fa-code',
                      'linkPosition' => explode(".",substr($path_file, strlen(explode("/",$path_file)[0].'/'.explode("/",$path_file)[1])+1))[0],
                      'tab' =>  !empty($tab) ? $tab : false
                    ];
                  }
                  $file->next();
                }
                if ( count($list) > 0 ) {
                  $totLines .= count($list);
                  // info for file who contain a code
                  $fileData = [
                    'text' => basename($path_file),
                    'icon' => 'nf nf-fa-file_code',
                    'num' => count($list),
                    'numChildren' => count($list),
                    'repository' => $info['repository']['bbn_path'].'/',
                    'uid' => $path.'/'.$file_name,
                    'file' => basename($path_file),
                    'link' => !empty($link) ? $link : false,
                    'tab' => !empty($tab) ? $tab : false,
                    'items' => $list
                  ];

                  $result[] = $fileData;
                }
              }
            }
          }
        }
      }
      if( !empty($result) ){
        $totFiles = 0;
        foreach ($result as $key => $value) {
          $totFiles = $totFiles + $value['items'][0]['numChildren'];
        }
        return [
          'list' => array_values($result),
          'occurences' => $occourences,
          'totFiles' => $tot_num_files,
          'filesFound' => count($result),//$tot_num_files++,
          'totLines' => $totLines
        ];
      }
    }
    return false;
  }

  public function searchAll(string $seek){
    if ( isset($seek) ) {
      $res = [];
      $occourences = 0;
      $totalFiles =  0;
      $numRepositories = 0;
      $foundRepos =[];
      foreach ( $this->repositories as $rep ) {
        //temporaney 
        if ( $rep['root'] !== 'cdn' ){
          $totalFiles += $this->fs->get_num_files($rep['root_path']);
          if ( $found = $this->fs->search($seek, $rep['root_path'], true, false, 'js|php|less|html') ) {
            foreach ( $found as $fn => $val ){
              $list = [];
              // case file into folder
              if ( $this->fs->is_file($fn) ){
                $path_file=  $val;
                //object initialization with every single file to check the lines that contain it
                $file = new \SplFileObject($fn);
                $totLines = 0;
                //cycle that reads all the lines of the file, it means until it has finished reading a file
                while( !$file->eof() ){
                  //current line reading
                  $lineCurrent = $file->current();
                  //if we find what we are looking for in this line and that this is not '\ n' then we will take the coirispjective line number with the key function, insert it into the array and the line number
                  if ( !empty($position = strpos($lineCurrent, $seek) !== false) && (strpos($lineCurrent, '\n') === false) ){
                    $lineNumber = $file->key()+1;
                    $name_path = $rep['path'].substr(dirname($val), strlen($base_rep));
                    $line = "<strong>".'line ' . $lineNumber . ' : '."</strong>";

                    $text = $line;
                    $text .= str_replace($seek, "<strong><span class='underlineSeach'>".$seek."</span></strong>", $lineCurrent);
                    $file_name = basename($path_file);

                    $occourences = $occourences + substr_count($lineCurrent, $seek);
                    if ( in_array($rep['name'], $foundRepos) === false ){
                      $foundRepos[] =  $rep['name'];
                      $numRepositories++;
                    }
                    // info for code
                    $list[] = [
                      'text' => strlen($text) > 1000 ? $line."<strong><i>"._('content too long to be shown')."</i></strong>" : $text,
                      'line' =>  $lineNumber-1,
                      'position' => $position,
                     // 'link' => $link,
                      'tab' =>  !empty($tab) ? $tab : false,
                      'code' => true,
                      'uid' => $rep['path'].'/'.$file_name,
                      'icon' => 'nf nf-fa-code'
                    ];
                  }
                  //next line
                  $file->next();
                }
                //if we find rows then we will create the tree structure with all the information
                if ( count($list) > 0 ){
                  $totLines = $totLines + count($list);
                  if ( explode("/",$path_file)[1] === "public" ){
                    $tab = 'php';
                  }
                  else {
                    $tab = explode("/",$path_file)[1];
                  }
                  $link =  explode(".",substr($path_file, strlen(explode("/",$path_file)[0].'/'.explode("/",$path_file)[1])+1))[0];
                }
                //info file
                $ext = \bbn\str::file_ext($fn,0);
                $fileData = [
                  'text' =>  $rep['name'].'/'.substr($fn, strlen($rep['root_path']))."&nbsp;<span class='bbn-badge bbn-s bbn-bg-lightgrey'>".count($list)."</span>",
                  'icon' => 'nf nf-fa-file_code_o',
                  'numChildren' => count($list),
                  'repository' => $rep['name'],
                  'uid' => $rep['name'].'/'.substr($fn, strlen($rep['root_path'])),
                  'file' => basename($fn),
                  'items' => $list,
                ];

                $path = explode('/', substr($fn, strlen($rep['root_path'])));
                //die(var_dump("sss", $path));
                if ( $path[0] === 'mvc' ){
                  if ( $path[1] === "public" ){
                    $tab = 'php';
                  }
                  else{
                    $tab = $path[1];
                  }
                }
                elseif ( $path[0] === 'components' ){
                  $tab = $ext;
                  $components = true;
                }
                unset($path[1]);
                $path = implode('/', $path);

                $link = $components === true ? $rep['name'].'/'.substr($path, 0,  strpos($path,'.'.$ext)).'/'.basename($path, '.'.$ext) : $rep['name'].'/'.substr($path, 0,  strpos($path,'.'.$ext));
                $fileData['tab'] = !empty($tab) ? $tab : false;
                $fileData['link'] = $link;
                foreach ( $fileData['items'] as &$item ){
                  $item['link'] = $link;
                  $item['tab'] =   !empty($tab) ? $tab : false;
                }
                $result[] = $fileData;
              }
            }
          }
        }
      }
    }
    if ( !empty($result) ){
      return [
        'list' => array_values($result),
        'occurences' => $occourences,
        'totFiles' => $totalFiles,
        'filesFound' => count($result),
        'repositoriesFound' => $numRepositories,
        'totalRepositories' => count($this->repositories),
        'totLines' => $totLines
      ];
    }
    else {
      return ['success'  => false];
    }
  }
}