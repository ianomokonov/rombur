<?php
class FilesUpload{
    public function upload($file, $path, $prefix, $baseUrl){
        if(!file_exists($path)){
            mkdir($path);
        }
        if($file != null){
            $n = basename($prefix."_".$file['name']);
            $d = $path."/".$n;
            if(move_uploaded_file($file['tmp_name'], $d)){
                return($baseUrl."/".$d);
            }else{
                throw new Exception('Отсутствует имя файла', 400);
            }
        }
    }

    public function removeFile($filelink, $baseUrl){
        $path = explode($baseUrl, $filelink);
        if(isset($path[1]) && file_exists($path[1])){
            unlink($path[1]);
        }
    }
}
