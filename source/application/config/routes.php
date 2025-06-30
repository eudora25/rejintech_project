<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'welcome';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// API 라우팅
$route['api/test'] = 'api/test/index';
$route['api/test/database'] = 'api/test/database';
$route['api/test/params'] = 'api/test/params';
$route['api/test/echo'] = 'api/test/echo_post';

// 인증 API 라우팅
$route['api/auth/login'] = 'api/auth/login';
$route['api/auth/verify'] = 'api/auth/verify';
$route['api/auth/profile'] = 'api/auth/profile';
$route['api/auth/check-login'] = 'api/auth/check_login';
$route['api/auth/logout'] = 'api/auth/logout';
$route['api/auth/change-password'] = 'api/auth/change_password';
$route['api/auth/login-logs'] = 'api/auth/login_logs';
$route['api/auth/login-statistics'] = 'api/auth/login_statistics';

// Swagger 문서 라우팅
$route['api/docs'] = 'api_docs/index';
$route['api/docs/openapi.json'] = 'api_docs/openapi_json';
$route['api/docs/generate'] = 'api_docs/generate';

// Welcome 컨트롤러 라우팅
$route['database-test'] = 'welcome/database_test';

// 사용자 API 라우팅 (예제)
$route['api/users'] = 'api/users/index';
$route['api/users/(:num)'] = 'api/users/get/$1';

// 조달청 데이터 조회 API 라우팅
$route['api/procurement/delivery-requests'] = 'api/procurement/delivery_requests';
$route['api/procurement/debug-delivery-requests'] = 'api/procurement/debug_delivery_requests';
$route['api/procurement/statistics/institutions'] = 'api/procurement/institution_statistics';
$route['api/procurement/statistics/companies'] = 'api/procurement/company_statistics';
$route['api/procurement/statistics/products'] = 'api/procurement/product_statistics';
$route['api/procurement/filter-options'] = 'api/procurement/filter_options';

// 배치 작업 라우팅
$route['batch/procurement/sync'] = 'batch/Procurement_sync/sync_delivery_requests';
$route['batch/procurement/status'] = 'batch/Procurement_sync/status';
$route['batch/procurement/test'] = 'batch/Procurement_sync/test_api';

// 데이터 정규화 배치 작업 라우팅
$route['batch/data_normalization/normalize'] = 'batch/data_normalization/normalize_delivery_data';
$route['batch/data_normalization/status'] = 'batch/data_normalization/status';
