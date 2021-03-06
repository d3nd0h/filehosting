@extends('admin.master')

@section('content')
    @if(Session::has('message'))
    <div class="row">
        <div class="col-md-6 col-xs-12">
            <p class="alert alert-success">
                {{ Session::get('message') }}
            </p>
        </div>
    </div>
    @endif
    <div class="row">
        <div class="col-md-12 col-xs-12">
            <div class="panel panel-default">
            <!-- Default panel contents -->
                <div class="panel-heading">List of @if(isset($files->username)){{$files->username}}@else {{'All'}}@endif Files</div>
                <!-- Table -->
                <!-- <div class="panel-body"> -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-stripped table-hover" id="dataTables">
                            <thead>
                                <tr>
                                    <td>#</td>
                                    <td>Folder Name</td>
                                    <td>File Name</td>
                                    <td>Extension</td>
                                    <td>Link</td>
                                    <td>File Size</td>
                                    @if(!isset($files->username))<td>Owner</td>@endif
                                    <td colspan="2">Action</td>
                                    <td>Revisions</td>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                                $rownum=1;
                                if(isset($files->username))$usernameFlag=1;
                                else $usernameFlag=0;
                            ?>
                                @foreach($files as $file)
                                <?php
                                    $targetdir = storage_path('files/'.$file->id_user.'/'.$file->folder_key.'/'.$file->key);
                                    $filename = $file->origFilename.'.'.$file->extension;
                                ?>
                                    <tr>
                                        <td>{{ $rownum++ }}</td>
                                        <td>{{ $file->folder_name }}</td>
                                        <td>{{ $file->filename }}</td>
                                        <td>{{ '.'.$file->extension }}</td>
                                        <td><a href="{{ route('files.show', array('key' => $file->key)) }}" >{{ route('files.show', array('key' => $file->key)) }}</a></td>
                                        <td>
                                            {{ $file->filesize }}
                                        </td>
                                        @if(!$usernameFlag)
                                        <td>
                                            {{ $file->id_user }}
                                        </td>
                                        @endif
                                        <td>
                                            {{ Form::open(array('method' => 'delete', 'url' => route('files.destroy', array('key' => $file->key)),
                                                'onClick' => 'return confirm(\'Are you sure you want to delete '.$file->filename.'.'.$file->extension.'?\')' )) }}
                                                {{ Form::submit("Delete", array('class' => 'btn btn-danger btn-sm', 'type' => 'submit')) }}
                                            {{ Form::close() }}
                                        </td>
                                        <td>
                                            <a href="{{ route('files.edit', array('key' => $file->key)) }}">
                                                {{ Form::button('Edit', ['class' => 'btn btn-success btn-sm'])}}
                                            </a>
                                        </td>
                                        <td>
                                            <a href="{{ route('revisions.show', array('key' => $file->key)) }}">
                                                {{ Form::button('Show', ['class' => 'btn btn-default btn-sm']) }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                <!-- </div> -->
            </div>
            @if(method_exists($files, 'links')){{ $files->links() }}@endif
        </div>
    </div>
@stop