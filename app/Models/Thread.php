<?php

namespace App\Models;

use App\Exceptions\CouldNotMarkReplyAsSolution;
use App\Helpers\HasSlug;
use App\Helpers\HasAuthor;
use App\Helpers\HasTimestamps;
use App\Helpers\ModelHelpers;
use App\Helpers\ReceivesReplies;
use App\Helpers\HasTags;
use DB;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Thread extends Model implements ReplyAble
{
    use HasAuthor, HasSlug, HasTimestamps, ModelHelpers, ReceivesReplies, HasTags;

    const TABLE = 'threads';

    /**
     * {@inheritdoc}
     */
    protected $table = self::TABLE;

    /**
     * {@inheritdoc}
     */
    protected $fillable = ['subject', 'body', 'ip', 'slug'];

    public function id(): int
    {
        return $this->id;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function excerpt(int $limit = 100): string
    {
        return str_limit(strip_tags(md_to_html($this->body())), $limit);
    }

    public function solutionReply(): ?Reply
    {
        return $this->solutionReplyRelation;
    }

    public function solutionReplyRelation(): BelongsTo
    {
        return $this->belongsTo(Reply::class, 'solution_reply_id');
    }

    public function isSolutionReply(Reply $reply): bool
    {
        if ($solution = $this->solutionReply()) {
            return $solution->id() === $reply->id();
        }

        return false;
    }

    public function markSolution(Reply $reply)
    {
        $thread = $reply->replyAble();

        if (! $thread instanceof Thread) {
            throw CouldNotMarkReplyAsSolution::replyAbleIsNotAThread($reply);
        }

        $this->solutionReplyRelation()->associate($reply);
        $this->save();
    }

    public function unmarkSolution()
    {
        $this->solutionReplyRelation()->dissociate();
        $this->save();
    }

    public function delete()
    {
        $this->removeTags();
        $this->removeReplies();

        parent::delete();
    }

    public static function findForForum(int $perPage = 20): Paginator
    {
        return static::findForForumQuery()->paginate($perPage);
    }

    public static function findForForumByTag(Tag $tag, int $perPage = 20): Paginator
    {
        return static::findForForumQuery()
            ->join('taggables', function ($join) use ($tag) {
                $join->on('threads.id', 'taggables.taggable_id')
                    ->where('taggable_type', static::TABLE);
            })
            ->where('taggables.tag_id', $tag->id())
            ->paginate($perPage);
    }

    /**
     * This will order the threads by creation date and latest reply.
     */
    private static function findForForumQuery(): Builder
    {
        return static::leftJoin('replies', function ($join) {
                $join->on('threads.id', 'replies.replyable_id')
                    ->where('replies.replyable_type', static::TABLE);
            })
            ->orderBy('latest_creation', 'DESC')
            ->groupBy('threads.id')
            ->select('threads.*', DB::raw('CASE WHEN COALESCE(MAX(replies.created_at), 0) > threads.created_at THEN COALESCE(MAX(replies.created_at), 0) ELSE threads.created_at END AS latest_creation'));
    }
}
