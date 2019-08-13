<?php

/**
 * Class RepositoryGit
 * 
 *
 */
namespace bbn\api;
use bbn;
use function GuzzleHttp\json_encode;

class git extends \Cz\Git\GitRepository
{ 
  public function listRemote(){
    return $this->extractFromCommand('git remote -v');
  }
  
  public function createRepositoryRemote(string $token, array $scope, string $api="gitbucket"){
    if ( $token &&
        !empty($scope['name']) &&
        $api
    ){
      if ( $api === 'gitbucket' ){
        $api = "https://git.bbn.so/api/v3/user/repos";
      }      
      //todo ceck create repository remote with this api for github
      /*else if ( $api === 'github' ){
        $api = ttps://api.github.com/users/vitof84/repos
      }*/
      try{
    		$res = \bbn\x::curl($api,json_encode($scope),[
          'post' => 1,
          'HTTPHEADER' => ['Authorization: token  '.$token]      
        ]);
        return true;
      }  
      catch(Exception $e) {
      	return false;
   		}
    }    
  }
  
	public function diff(){
    $output = $this->extractFromCommand('git status -s');
    if ( is_array($output) && !empty($output) ){
      $arr = [];
        
      foreach ( $output as $i => $val ){
      
        $sigle = substr($val, 0, 2); 
                
        $element =[
        	'file' => false,
          'folder' => false,
          'action' => false,
          'commit' => false, 
          'added' => (strpos($val, 'A') == 0) ? true : false,
          'other' => false
      	];
          
        // for name file   
        if ( strpos($val, '"') == 3){
          if ( substr($val, -1, 1) === "/" ){
            $element['folder'] = substr($val, 4);  
          }
          else{
          	$element['file'] = substr($val, 4);   
          }         	
        }
        else{
          if ( substr($val, -1, 1) === "/" ){
            $element['folder'] = substr($val, 3);  
          }
          else{
          	$element['file'] = substr($val, 3);   
          }           
        }
        
        //for action and assign value for only file 
        switch( $sigle ){
          case '??':
            $element['action'] = 'untracked';
            $element['commit'] = false;
            $element['added'] = false;            
          break;
          case 'D ':
            $element['action'] = 'deleted';
            $element['commit'] = true;
            $element['added'] = true;            
          break;
          case ' D':
            $element['action'] = 'deleted';                        
            $element['commit'] = false;
            $element['added'] = false;            
          break;
          case 'R ':
            $files = explode(' -> ', $file);
            $element['action'] = 'renamed';
            $elemnt['old_file'] = $files[0];
            $elemnt['new_file'] = $files[1];
            $element['commit'] = true;
            $element['added'] = true;            
          break;
          case 'M ':
            $element['action'] = 'updated';
            $element['commit'] = true;
            $element['added'] = true;            
          break;
          case ' M':
            $element['action'] = 'update';
            $element['commit'] = false;
            $element['added'] = false;            
          break;
          default: 
            $element['other'] = $sigle === "A " ? "'A ' (Only added)" : $sigle;  
        }
        $arr[]= $element;
      }
    }
    return $arr;
	}
  
  
  //example $git->pushInRemote('vitof84/test', username, password) 
  public function pushInRemote(string $repository, string $user, string $passw, string $server="github.com"){
    if ($repository && $user && $passw && $server){
      $remote = NULL;
      $params['--repo'] = 'https://'.$user.':'.$passw.'@'.$server.'/'.$user.'/'.$repository.'.git';  
      try{
        $output = $this->begin()
          ->run("git push $remote", $params)          
          ->end();
  		}
    	catch(Exception $e) {
        die(var_dump($e->getMessage(), $params['--repo']));
     		return false;
   		}
      return is_object($output);
		}
    return false;
  } 
  
  public function removeLocalBranch(string $branch){
    if ( $branch &&  ($this->getCurrentBranchName() !== $branch) ){
      try{
	  	  $output = $this->begin()
  	      	      ->run("git branch -d $branch")
    	    	      ->end();
     	}
    	catch(Exception $e) {
     		return false;
   		}  
    	return is_object($output);
    }
    return false;
  }
  
  public function removeRemoteBranch( string $repository, string $branch, string $user, string $passw, string $server="github.com" ){
    if ( $repository && $branch && $user && $passw && $server ){     
       	$remote = 'https://'.$user.':'.$passw.'@'.$server.'/'.$repository.'.git';
        try{
      		$output = $this->begin()
                		->run("git push --delete $remote $branch")
       	 	          ->end();
    		}
    		catch(Exception $e) {
      		return false;
   			}
      	return is_object($output);
		}
    return false;
  }
  
  public function createRemoteRepository(string $repository, string $user, string $passw, string $localPath, string $server="github.com"){
    if ( $repository && $user && $passw && $server && $localPath ){            	
			$rep = self::init($localPath);
      if ( is_object($rep) ){
        $remote = 'https://'.$server.'/'.$user.'/'.$repository.'.git';  
        if ( !empty($this->addRemote('origin', $remote)) ){
         	file_put_contents($localPath.'/README.md', '#README'); 
          if ( !empty($this->addAllChanges()) ){
            if ( !empty($this->commit("New Repository")) ){
           	  return $this->pushInRemote($repository, $user, $passw, $server);     					
           	}                        
          }                  
        }
			}
    }
    return false;
  }
  
  /* da completare*/
 
  public function difference(){
    $diff= $this->extractFromCommand('git diff --word-diff');
    $arr= [];
    $status = [
      '@@ -1 +0,0 @@' => 'delete in local',
      '@@ -1 +1 @@' => 'different',
      '@@ +1 -1 @@' => 'different',
      '@@ 0,0 -1 @@' => 'delete in remote',
    ];
    
    foreach ($diff as $i => $ele){
             

      $start = strpos($ele, '--git');
      
      if ( $start != 0){
        $idx = 0;
        $file = substr($ele, $start+5);
        $file = substr($file, strpos($file, 'a/')+2, strpos($file, 'b/')-3);
				
        $idx= $i+5;
        
        if ( strpos($diff[$i + 5], '@@') === false ){
        	$idx--;
        }
        if ( !empty($diff[$idx+1]) ){
        
          $x = strpos($diff[$idx+1],'-]{+') === false ? '-]' : '-]{+';
          
          $remote_code = false;
          $local_code = false;
          
          //for code remote
          if ( $x === '-]{+' ){   
   					$code = substr($diff[$idx+1],strpos($diff[$idx+1],$x)+4);    			
         	  $remote_code = substr($code,0, strpos($code,'+}'));                                
          }
          
          //for code local
          if ( strpos($diff[$idx+1], '[-') !== false ){
            $code = substr($diff[$idx+1],  strpos($diff[$idx+1], '[-')+2);
            $local_code = substr($code,0, strpos($code, $x));                                
          }  
          $arr[] = [
            'file' => $file,
            'status' => $status[$diff[$idx]],
            'code' => [
              'local' => $local_code,
              'remote'=> $remote_code   
            ]
          ];        
      	}
    	}
      
    }  
    return $arr;    
  }
  
  public function logs(int $start  = 0, int $limit = 0): Array
  { 
    /*
     for get info the commits
      %n new line,
      %an author,
      %h hash commit abbrev.
      %H hash commit
      %ad date
      %cN committer
      %N note commit
    */    
    $cmd = 'git log --pretty=format:"%h%n%H%n%an%n%s%n%ae%n%ad%n%cN%n%N%n__commit__" --date=format-local:"%Y-%m-%d %H:%M:%S" --skip='.$start;
    
    if ( $limit > 0 ){
      $cmd .= ' --max-count='.$limit;      
    }
    $field = ['sha1', 'commit', 'author', 'title_commit', 'email_author', 'date', 'committer', 'notes'];
    $commits = [];
    $arr = [];
    $i = 0;    
    $logs = $this->extractFromCommand($cmd);

    foreach( $logs as $val ){
      if ( $val !== '__commit__' ){
        $arr[$field[$i]] = $val;
        $i++;        
      }
      else {
        $commits[] = $arr;        
        $i = 0;
      }
    }

    return [
      'commits' => $commits,
      'total' => (int)$this->extractFromCommand('git rev-list --all --count')[0]
    ];
  }
}