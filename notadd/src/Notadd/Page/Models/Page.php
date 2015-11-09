<?php
/**
 * This file is part of Notadd.
 * @author TwilRoad <269044570@qq.com>
 * @copyright (c) 2015, iBenchu.org
 * @datetime 2015-10-30 17:13
 */
namespace Notadd\Page\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Notadd\Page\Events\GetTemplateList;
class Page extends Model {
    use SoftDeletes;
    /**
     * @var array
     */
    protected $fillable = [
        'parent_id',
        'title',
        'thumb_image',
        'alias',
        'keyword',
        'description',
        'template',
        'content',
        'enabled',
        'order_id',
        'view_count',
    ];
    public function countSubPages() {
        $count = parent::whereParentId($this->attributes['id'])->count();
        return $count ? $count : 0;
    }
    public static function getCrumbMenu($parent_id, &$crumb) {
        if($parent_id == 0) {
            return $crumb;
        } else {
            $parent = parent::find($parent_id);
            array_unshift($crumb, $parent);
            if($parent['parent_id']) {
                static::getCrumbMenu($parent['parent_id'], $crumb);
            }
        }
    }
    public function getTemplateList() {
        $templates = Collection::make();
        $templates->put('default::page.default', '默认模板');
        static::$dispatcher->fire(new GetTemplateList($templates));
        return $templates;
    }
    public function hasParent() {
        return $this->getAttribute('parent_id') && parent::whereId($this->getAttribute('parent_id'))->count();
    }
    public function parent() {
        return $this->belongsTo(Page::class, 'parent_id');
    }
}