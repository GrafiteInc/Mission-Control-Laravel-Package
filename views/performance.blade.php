@extends('layouts.main')

@section('page-title', 'Application Up')

@section ('app-content')
    <div class="container">
        <div class="row">
            <div class="col bmx-vh-50">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <div class="bmx-mt-n5">
                    <div class="card shadow-sm bmx-tada">
                        <div class="card-body bg-body-tertiary rounded text-center">
                            <h2>
                                <span class="fa fa-heart-pulse text-success"></span>
                                Application Performance <span class="d-hide">{{ $result }}</span>
                            </h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
