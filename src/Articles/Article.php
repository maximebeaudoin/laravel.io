<?php namespace Lio\Articles;

use Illuminate\Database\Eloquent\Model;
use Lio\Articles\Events\ArticleWasComposed;
use Lio\Events\EventGenerator;
use Lio\Tags\Taggable;

class Article extends Model
{
    use Taggable, EventGenerator;

    const STATUS_DRAFT = 0;
    const STATUS_PUBLISHED = 1;

    protected $table      = 'articles';
    protected $with       = ['author'];
    protected $guarded    = [];
    protected $dates      = ['published_at'];
    protected $softDelete = true;

    public $presenter = 'Lio\Articles\ArticlePresenter';

    public function author()
    {
        return $this->belongsTo('Lio\Accounts\User', 'author_id');
    }

    public function comments()
    {
        return $this->morphMany('Lio\Comments\Comment', 'owner');
    }

    public static function compose($author, $title, $content, $status, $laravelVersion, array $tagIds = [])
    {
        $article = new static([
            'author_id' => $author->id,
            'title' => $title,
            'content' => $content,
            'status' => $status,
            'laravel_version' => $laravelVersion,
        ]);
        $article->setTagsById($tagIds);
        $article->raise(new ArticleWasComposed($article));
        return $article;
    }

    public function edit($title, $content, $laravelVersion, array $tagIds = [])
    {
        $this->title = $title;
        $this->content = $content;
        $this->laravelVersion = $laravelVersion;
        $this->setTagsById($tagIds);
    }

    public function updateCommentCount()
    {
        $this->comment_count = $this->comments()->count();
        $this->save();
    }

    public function isManageableBy($user)
    {
        if ( ! $user) return false;
        return $this->isOwnedBy($user) || $user->isArticleAdmin();
    }

    public function isOwnedBy($user)
    {
        if ( ! $user) return false;
        return $user->id == $this->author_id;
    }

    public function isPublished()
    {
        return $this->exists && $this->status == static::STATUS_PUBLISHED;
    }

    public function hasBeenPublished()
    {
        return ! is_null($this->published_at);
    }

    public function createSlug()
    {
        $authorName = $this->author->name;
        $date       = date("m-d-Y", strtotime($this->published_at));
        $title      = $this->title;

        return \Str::slug("{$authorName} {$date} {$title}");
    }

    public function setDraft()
    {
        if ($this->exists && $this->isPublished()) {
            $this->status = static::STATUS_DRAFT;
        }
        $this->save();
    }

    public function publish()
    {
        if ($this->exists && ! $this->isPublished()) {
            $this->status = static::STATUS_PUBLISHED;
            $this->slug = $this->createSlug();

            if ( ! $this->hasBeenPublished()) {
                $this->published_at = date('Y-m-d H:i:s');
            }

            $this->save();
        }
    }

    public function save(array $options = array())
    {
        if ($this->status == static::STATUS_PUBLISHED && ! $this->published_at) {
            $this->published_at = $this->freshTimestamp();
        }

        return parent::save($options);
    }
}