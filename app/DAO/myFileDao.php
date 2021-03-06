<?php

use Carbon\Carbon;

class myFileDao extends myFile{

    private function formatFileSize($size){
        $bytes = $size;
        $precision = 2;
        $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
        $bytes = max($bytes, 0); 
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow)); 
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function getFileByKey($key){
        return $this->where('key', $key)
                    ->where('is_active', true)
                    ->get()
                    ->first();
    }

    public function deleteFile($key){
        $query = $this->where('key', $key);
        $rows = $query->get();
        $old_values = '';
        $first = 1;
        foreach ($rows as $row) {
            if(!$first){
                $old_values .= ', ';
            }
            if($row->is_active){
                $filename = $row->filename.'.'.$row->extension;
            }
            $old_values .= $row->origFilename.'.'.$row->extension;
            $first=0;
        }
        $query->delete();
        LogDao::logDelete($this->table, $old_values);
        return $filename;
    }

    public function deleteFilesByOwnership($id){
        try {
            $query = $this->join('keys', 'keys.key', '=', 'files.key')
                        ->where('id_user', $id);
            $rows = $query->get();
            if(!$rows->isEmpty()){
                $old_values = '';
                $first = 1;
                foreach($rows as $row){
                    if(!$first){
                        $old_values .= ', ';
                    }
                    $old_values .= $row->filename.'.'.$row->extension;
                    $first = 0;
                }
                LogDao::logDelete($this->table, $old_values);
                $query->delete();
            }
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    public function deleteFilesinFolder($folder_key){
        $query = $this->join('keys', 'keys.key', '=', 'files.key')
                    ->whereExists(function($query) use ($folder_key){
                        $query->select('id')
                            ->from('keys')
                            ->whereRaw('files.key = keys.key')
                            ->where('keys.folder_key', '=', $folder_key);
                    });
        $rows = $query->get();
        $old_values = "";
        $first = 1;
        foreach($rows as $row){
            if(!$first){
                $old_values .= ', ';
            }
            $old_values .= $row->id;
            $first = 0;
        }
        LogDao::logDelete($this->table, $old_values);
        return $query->delete();
    }

    public function deleteGuestFiles($length, $interval){
        try {
            $id = 2;
            $query = $this->join('keys', 'keys.key', '=', 'files.key')
                        ->where('id_user', $id)
                        ->whereRaw('created_at < date_sub(now(), interval '.$length.' '.$interval.' )');
            $rows = $query->get();
            if(!$rows->isEmpty()){
                $old_values = '';
                $first = 1;
                foreach($rows as $row){
                    if(!$first){
                        $old_values .= ', ';
                    }
                    $old_values .= $row->filename.'.'.$row->extension;
                    $first = 0;
                }
                LogDao::logDelete($this->table, $old_values);
                $query->delete();
            }
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    public function getFileInfo($details){
        $file = $this->getFileByKey($details->key);
        $parent_folder = $details->folder_key;
        $id = $details->id_user;
        $targetDir = storage_path('files/'.$id.'/'.$parent_folder.'/'.$details->key);
        $fileName = $file->origFilename.'.'.$file->extension;

        $ret = new stdClass();
        $ret->path = $details->path;
        $ret->key = $file->key;
        $ret->fileName = $file->filename.'.'.$file->extension;
        $ret->filesize = $file->filesize;
        $ret->id_user = $file->id_user;
        $ret->extension = $file->extension;
        return $ret;
    }

    public function getFilePath($details){
        $ret = new stdClass();
        $file = $this->getFileByKey($details->key);
        $id = $details->id_user;
        $targetDir = storage_path('files/'.$id.'/'.$details->folder_key.'/'.$file->key);
        $fileName = $file->origFilename.'.'.$file->extension;
        $ret->path = $targetDir.'/'.$fileName;
        $ret->fileName = $file->filename.'.'.$file->extension;
        return $ret;
    }

    public function getRevisionHistory($key){
        return $file = $this->where('key', $key)
                ->get();
    }
    
    public function isValidId($key, $id){
        return $this->where('key', $key)
                ->where('id', $id)
                ->get()
                ->count();
    }

    public function moveFile($file, $fileKey, $fileHist){
        $main_dir = storage_path('files/');
        $target_dir = $main_dir.$fileKey->id_user.'/'.$file['folder'].'/'.$fileKey->key;
        if(!file_exists($target_dir))mkdir($target_dir);
        $file['file']->move($target_dir, $fileHist->origFilename.'.'.$fileHist->extension);
    }

    public function renameFile($key){
        $query = $this->where('key', $key)
                ->where('is_active', true);
        $row = $query->get()->first();
        $query->update(array('filename' => Input::get('fileName')));
        LogDao::logEdit($this->table, 'filename', $row->filename, Input::get('fileName'));
    }

    public function reviseFile($file,myFile $fileHist){
        try{
            $this->where('key', $fileHist->key)
                ->update(array(
                    'is_active' => false,
                ));
        }
        catch (\Exception $e){
            return false;
        }
        return $this->saveFile($file, $fileHist);
    }

    public function setActive($key, $id){
        $prev = $this->where('key', $key)->where('is_active', true)->pluck('id');
        $this->where('key', $key)
                ->update(array(
                    'is_active' => false
                ));
        $this->where('id', $id)
            ->update(array(
                    'is_active' => true
                ));
        LogDao::logEdit($this->table, 'is_active', $prev, $id);
    }

    public function saveFile($file,myFile $hist){
        $hist->extension = $file['file']->getClientOriginalExtension();
        $hist->filename = basename($file['file']->getClientOriginalName(), '.'.$hist->extension);
        $hist->filesize = $this->formatFileSize($file['file']->getSize());
        $timestamp = Carbon::now()->format('Y_m_d_h_i_s_');
        $hist->origFilename = $timestamp.$hist->filename;
        $hist->is_active = 1;
        LogDao::logCreate($this->table, 'uploadedFilename', $file['file']->getClientOriginalName());
        return $hist->save();
    }
}
?>