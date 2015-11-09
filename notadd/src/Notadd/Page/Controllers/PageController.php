<?php
/**
 * @author TwilRoad <269044570@qq.com>
 * @copyright (c) 2015, iBenchu.com
 */
namespace Notadd\Page\Controllers;
use Notadd\Foundation\Routing\Controller;
use Notadd\Page\Events\OnPageShow;
use Notadd\Page\Page;
class PageController extends Controller {
    public function show($id) {
        $page = new Page($id);
        $this->app->make('events')->fire(new OnPageShow($this->app, $this->view, $page));
        $template = $page->getTemplate();
        if($template) {
            $this->app->make('view')->exists($template) || $template = 'default::page.default';
        } else {
            $template = 'default::page.default';
        }
        $this->share('logo', file_get_contents(realpath($this->app->basePath() . '/../template/install') . DIRECTORY_SEPARATOR . 'logo.svg'));
        return $this->view($template);
    }
}