<?php

global $prefix;

include_once("$prefix/SectionBody.php");
include_once("$prefix/SectionAccount.php");
include_once("$prefix/image.php");

class SectionSetup extends SectionBody
{

var $stage=0;

function SectionSetup()
{
  global $db;
  $this->name="setup";

  $sql="show tables;";
  $result=$db->query($sql, true);
  if (!$result) 
  {
    $this->stage=0;
    return;
  }

  $sql="select id,name from $db->user where name='admin'";
  $result=$db->query($sql, true);
  if (!$result || mysql_num_rows($result)==0)
  {
    $this->stage=1;
    return;
  }
  
  $this->stage=2;
}

function exec_stage_db()
{
  // check sql parameters
  global $db;
  $result=$db->test_database($_REQUEST['host'], 
                 $_REQUEST['user'], 
                 $_REQUEST['password'], 
                 $_REQUEST['database']);
  if ($result!=true)
  {
    $this->error($result);
    return false;
  }
  
  $configdir=getcwd()."/data";
  if (!is_writeable($configdir))
  {
    $this->error("Could not write to config directory $configdir");
    return false;
  }
  
  // check for writing the minimalistic configure file
  $config="$configdir/vars.inc";
  
  // write minimalistic configuration file
  $f=fopen($config, "w");
  if (!$f) 
  {
    $this->error("Could not write to config file $config");
    return false;
  }

  fwrite($f, "# Configuration file\n");
  fwrite($f, "db_host=".$_REQUEST['host']."\n");
  fwrite($f, "db_user=".$_REQUEST['user']."\n");
  fwrite($f, "db_password=".$_REQUEST['password']."\n");
  fwrite($f, "db_database=".$_REQUEST['database']."\n");
  fwrite($f, "# Prefix of phTagr tables.\n");
  fwrite($f, "db_prefix=".$_REQUEST['prefix']."\n");
  fclose($f);
  
  if (!$db->connect($config))
  {
    $this->error("Could not read the configuration file $config");
    // remove the configuration file
    unlink($config);
    return false;
  }
  
  if (!$db->create_tables())
  {
    $this->error("The tables could not be created successfully");
    // remove the configuration file
    unlink($config);
    return false;
  }
  
  $this->success("Configuration file and tables created successfully");
  $this->warning("Please move the file '$config' to the directory '".getcwd()."/phtagr'");
  
  if (!$this->init_tables())
  {
    $this->warning("Could not init the tables correctly");
    return false;
  }

  return true;
}

/** Insert default values to the table
  @return true on success. false on failure */
function init_tables()
{
  $dir=getcwd();
  global $db;

  // image cache
  $sql="INSERT $db->pref (userid, name, value) VALUES(0, 'cache', '$dir/cache')";
  $result=$db->query($sql);
  if (!$result) return false;

  // upload dir
  $sql="INSERT $db->pref (userid, name, value) VALUES(0, 'upload_dir', '$dir/data')";
  $result=$db->query($sql);
  if (!$result) return false;
  
  return true;
}

function exec_stage_pref()
{
  // check cache directory
  if (!is_dir($_REQUEST['cache']))
  {
    $this->error("Cache directory does not exists");
    return false;
  }
  if (!is_writeable($_REQUEST['cache']))
  {
    $this->error("Could not write to cache directory");
    return false;
  }
}

function print_stage_db()
{
  echo "<h3>Setup of mySQL database connection</h3>\n";
  
  $this->p("Please insert the connection data for the mysql connection data");
  
  echo "<form method=\"post\">
<input type=\"hidden\" name=\"section\" value=\"setup\" />
<input type=\"hidden\" name=\"stage\" value=\"0\" />
<input type=\"hidden\" name=\"action\" value=\"init\" />

<fieldset><legend><b>SQL Table</b></legend>
<table>
  <tr>
    <td>Host</td><td><input type=\"text\" name=\"host\" value=\"localhost\" /></td>
  </tr><tr>
    <td>User</td><td><input type=\"text\" name=\"user\" value=\"phtagr\" /></td>
  </tr><tr>
    <td>Password</td><td><input type=\"password\" name=\"password\" /></td>
  </tr><tr>
    <td>Database</td><td><input type=\"text\" name=\"database\" value=\"phtagr\" /></td>
  </tr><tr>
    <td>Table prefix</td><td><input type=\"text\" name=\"prefix\" value=\"\" /></td>
  </tr>
</table>
</fieldset>

<input type=\"submit\" value=\"OK\" />&nbsp;&nbsp;<input type=\"reset\" value=\"Reset\" />

";
  $this->info("The data will be stored in the directory ".getcwd()."/phtagr.
  For this reason, the directory should be writeable by the webserver. After
  this setup step, the permission should be set to read-only.");
  
  $this->info("To run multiple phTagr instances within one database, please use
  the table prefix. Usually this option is not used.");
}

function print_stage_admin()
{
  echo "<h3>Creation of Admin Account</h3>\n";
  $account=new SectionAccount();
  $account->user='admin';
  $account->print_form_new_account();
}

function print_actions()
{
  echo "<ul>\n";
  echo "<li><a href=\"index.php?section=setup&action=sync\">Synchronize</a> files with the database</li>\n";
  echo "<li><a href=\"index.php?section=setup&action=init\">Create a phTagr Instance</a></li>\n";
  echo "<li><a href=\"index.php?section=setup&action=delete_tables\">Delete Tables</a></li>\n";
  echo "<li><a href=\"index.php?section=setup&action=delete_images\">Delete all images</a></li>\n";
  echo "<li><a href=\"index.php?section=setup&action=upload_dir\">Set the upload directory</a></li>\n";
  echo "<li><a href=\"index.php\">Go to phTagr</a></li>\n";
  echo "</ul>\n";
}

function setup_upload()
{
  global $db;

  $request_upload_dir=$_REQUEST['set_dir'];
  if ($request_upload_dir != "")
  {
    // Check if upload is already exists
    $sql = "SELECT value 
            FROM $db->pref 
            WHERE name='upload_dir'";
    $result= $db->query($sql);
    if (!$result)
      return false;
    
    if (mysql_num_rows($result)>0)
      $sql="UPDATE $db->pref 
            SET value='${request_upload_dir}'
            WHERE name='upload_dir'";
    else
      $sql="INSERT INTO $db->pref (name, value) 
            VALUES('upload_dir', '${request_upload_dir}')";
    $result = $db->query ($sql);
    if (!$result)
    {
      $this->warning( "Could not update 'update_dir'!\n");
      return false;
    }
    else
      $this->success( "Update successful!\n");
  }

  $pref = $db->read_pref();
  $upload_dir = $pref['upload_dir'];

  echo "<h3>Uploads</h3>\n";
  echo "<form action=\"./index.php\" method=\"POST\">\n";
  echo "All uploads go below this folder. For each user a subfolder will be ";
  echo "created under which his images will reside. If a file exists, it ";
  echo "will be saved as FILENAME-xyz.EXTENSION.<br>\n";
  echo "<input type=\"hidden\" name=\"section\" value=\"setup\" />\n";
  echo "<input type=\"hidden\" name=\"action\" value=\"upload_dir\" />\n";
  echo "<input type=\"text\" name=\"set_dir\" value=\"" . $upload_dir . "\" size=\"60\"/>\n";
  echo "<input type=\"submit\" value=\"Save\" class=\"submit\" />\n";
  echo "</form>\n";
}

/** Synchronize files between the database and the filesystem. If a file not
 * exists delete its data. If a file is newer since the last update, update its
 * data. */
function sync_files()
{
  global $db;

  echo "<h3>Synchronize image data...</h3>\n";

  $this->info("This operation may take some time");
  
  $sql="SELECT id,filename
        FROM $db->image";
  $result=$db->query($sql);
  if (!$result)
    return;
    
  $updated=0;
  $deleted=0;
  while ($row=mysql_fetch_row($result))
  {
    $id=$row[0];
    $filename=$row[1];

    if (!file_exists($filename))
    {
      $this->delete_image_data($id,$filename);
      $deleted++;
    }
    else 
    {
      $image=new Image($id);
      if ($image->update())
        $updated++;
      unset($image);
    }
  }
  $this->p("All images are now synchronized. $deleted images are delted. $updated images are updated.");
}

/** Deletes a file from the database */
function delete_image_data($id, $file)
{
  global $db;
  echo "<div class='warning'>File '$file' does not exists. Deleting its data form database</div>\n";
  $sql="DELETE FROM $db->tag 
        WHERE imageid=$id";
  $result = $db->query($sql);

  $sql="DELETE FROM $db->image 
        WHERE id=$id";
  $result = $db->query($sql);
}


function print_content()
{
  global $db;
  global $user;
  
  echo "<h2>Setup</h2>\n";
  $action=$_REQUEST['action'];
  if ($action=='install')
  {
    $this->stage=0;
  }
  else if ($action=='init')
  {
    $this->exec_stage_db();
    $this->stage=1;
  }
  else if ($action=='sync')
  {
    $this->sync_files();   
  }
  else if ($action=='delete_images')
  {
    $db->delete_images();
    $this->warning('All image data are deleted');
    return;
  }
  else if ($action=='delete_tables')
  {
    $db->delete_tables();
    $this->warning('Tables deleted');
    return;
  }
  else if ($action=='upload_dir')
  {
    $this->setup_upload(); 
    return;
  }
  else if ($action=='create')
  {
    echo "<h2>Create A New Account</h2>\n";
    $name=$_REQUEST['name'];
    $password=$_REQUEST['password'];
    $confirm=$_REQUEST['confirm'];
    if ($password != $confirm) {
      $this->error("Password mismatch");             
      return;
    }
    $account=new SectionAccount();
    if ($account->create_user($name, $password)==true) {
      $this->success("User '$name' created");
    }
    return;
  }
  switch ($this->stage) {
  case 0: $this->print_stage_db(); break;
  case 1: $this->print_stage_admin(); break;
  default: $this->print_actions(); break;
  }
}

}
?>
