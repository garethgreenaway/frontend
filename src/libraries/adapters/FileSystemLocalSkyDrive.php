<?php
/**
 * Dropbox adapter that extends much of the FileSystemLocal logic
 *
 * This class defines the functionality defined by FileSystemInterface for a plain Filesystem.
 * @author Hub Figuiere <hub@figuiere.net>
 * @author Jaisen Mathai <jaisen@jmathai.com>
 */
class FileSystemLocalSkyDrive extends FileSystemLocal implements FileSystemInterface
{
  private $root;
  private $urlBase;
  private $skydrive;

  public function __construct()
  {
    parent::__construct();
    $fsConfig = getConfig()->get('localfs');
    $this->root = $fsConfig->fsRoot;
    $this->host = $fsConfig->fsHost;
    $this->skydrive = new FileSystemSkyDriveBase($this);
  }

  public function deletePhoto($photo)
  {
    return $this->skydrive->deletePhoto($photo) && parent::deletePhoto($photo);
  }

  public function downloadPhoto($photo)
  {
    return $this->skydrive->getFilePointer($photo);
  }

  /**
    * Gets diagnostic information for debugging.
    *
    * @return array
    */
  public function diagnostics()
  {
    return array_merge($this->skydrive->diagnostics(), parent::diagnostics());
  }

  /**
    * Executes an upgrade script
    *
    * @return void
    */
  public function executeScript($file, $filesystem)
  {
    if($filesystem == 'skydrive')
      echo file_get_contents($file);
    else
      parent::executeScript($file, $filesystem);
  }

  /**
   * Get photo will copy the photo to a temporary file.
   *
   */
  public function getPhoto($filename)
  {
    getLogger()->warn("=== Calling getPhoto from FileSystemLocalSkyDrive ===");
    return parent::getPhoto($filename);
  }

  public function putPhoto($localFile, $remoteFile, $dateTaken)
  {
    $parentStatus = true;
    if(strpos($remoteFile, '/original/') === false)
      $parentStatus = parent::putPhoto($localFile, $remoteFile, $dateTaken);

    return $this->skydrive->putPhoto($localFile, $remoteFile, $dateTaken) && $parentStatus;
  }

  public function putPhotos($files)
  {
    $parentFiles = array();
    foreach($files as $file)
    {
      list($localFile, $remoteFileArr) = each($file);
      $remoteFile = $remoteFileArr[0];
      $dateTaken = $remoteFileArr[1];
      if(strpos($remoteFile, '/original/') === false)
        $parentFiles[] = $file;
    }
    return $this->skydrive->putPhotos($files) && parent::putPhotos($parentFiles);
  }

  /**
    * Get the hostname for the remote filesystem to be used in constructing public URLs.
    * @return string
    */
  public function getHost()
  {
    return $this->host;
  }

  /**
    * Return any meta data which needs to be stored in the photo record
    * @return array
    */
  public function getMetaData($localFile)
  {
    return array();
  }

  public function initialize($isEditMode)
  {
    return $this->skydrive->initialize($isEditMode) && parent::initialize($isEditMode);
  }

  /**
    * Identification method to return array of strings.
    *
    * @return array
    */
  public function identity()
  {
    return array_merge(array('dropbox'), parent::identity());
  }

  public function normalizePath($path)
  {
    return parent::normalizePath($path);
  }
}
