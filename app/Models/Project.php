<?php

namespace App\Models;

use App\Models\Traits\ProjectAttributes;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Class Project.
 *
 * @author annejan@badge.team
 * @property int         $id
 * @property int         $category_id
 * @property int         $user_id
 * @property string      $name
 * @property string      $slug
 * @property int|null    $min_firmware
 * @property int|null    $max_firmware
 * @property string|null $git
 * @property string|null $git_commit_id
 * @property Carbon|null $published_at
 * @property Carbon|null $deleted_at
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 * @property int         $download_counter
 * @property-read Collection|Badge[] $badges
 * @property-read int|null $badges_count
 * @property-read string $category
 * @property-read Collection|Project[] $dependants
 * @property-read int|null $dependants_count
 * @property-read Collection|Project[] $dependencies
 * @property-read int|null $dependencies_count
 * @property-read string|null $description
 * @property-read string|null $description_html
 * @property-read string $revision
 * @property-read float $score
 * @property-read int $size_of_content
 * @property-read string $size_of_content_formatted
 * @property-read int $size_of_zip
 * @property-read string $size_of_zip_formatted
 * @property-read string $status
 * @property-read Collection|BadgeProject[] $states
 * @property-read int|null $states_count
 * @property-read User $user
 * @property-read string $author
 * @property-read Collection|Version[] $versions
 * @property-read int|null $versions_count
 * @property-read Collection|Vote[] $votes
 * @property-read int|null $votes_count
 * @property-read Collection|Warning[] $warnings
 * @property-read int|null $warnings_count
 * @property-read Collection|User[] $collaborators
 * @property-read int|null $collaborators_count
 * @property-read Collection|Team[] $teams
 * @property-read int|null $teams_count
 * @method static bool|null forceDelete()
 * @method static Builder|Project newModelQuery()
 * @method static Builder|Project newQuery()
 * @method static Builder|Project onlyTrashed()
 * @method static Builder|Project query()
 * @method static bool|null restore()
 * @method static Builder|Project whereCategoryId($value)
 * @method static Builder|Project whereCreatedAt($value)
 * @method static Builder|Project whereDeletedAt($value)
 * @method static Builder|Project whereDownloadCounter($value)
 * @method static Builder|Project whereGit($value)
 * @method static Builder|Project whereGitCommitId($value)
 * @method static Builder|Project whereId($value)
 * @method static Builder|Project whereName($value)
 * @method static Builder|Project wherePublishedAt($value)
 * @method static Builder|Project whereSlug($value)
 * @method static Builder|Project whereUpdatedAt($value)
 * @method static Builder|Project whereUserId($value)
 * @method static Builder|Project withTrashed()
 * @method static Builder|Project withoutTrashed()
 * @method static Builder|Project whereMaxFirmware($value)
 * @method static Builder|Project whereMinFirmware($value)
 * @mixin Eloquent
 */
class Project extends Model
{
    use SoftDeletes;
    use HasFactory;
    use ProjectAttributes;

    /**
     * Create with these.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'category_id',
    ];

    /**
     * Appended magic data.
     *
     * @var array<string>
     */
    protected $appends = [
        'revision',
        'size_of_zip',
        'size_of_content',
        'category',
        'description',
        'status',
        'author',
    ];

    /**
     * Hidden data.
     *
     * @var array<string>
     */
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
        'user_id',
        'id',
        'category_id',
        'pivot',
        'versions',
        'states',
        'git',
        'git_commit_id',
        'user',
    ];

    /**
     * DateTime conversion for these fields.
     *
     * @var array<string>
     */
    protected $dates = [
        'created_at', 'updated_at', 'deleted_at', 'published_at',
    ];

    /**
     * Magical methods that associate a user and make sure projects have an empty __init__.py added.
     */
    public static function boot(): void
    {
        parent::boot();

        static::creating(
            function($project) {
                if ($project->user_id === null) {
                    $user = Auth::guard()->user();
                    $project->user()->associate($user);
                }
            }
        );

        static::created(
            function($project) {
                $version = new Version();
                $version->revision = 1;
                $version->project()->associate($project);
                $version->save();
                if ($project->git === null) {
                    // add first empty python file :)
                    $file = new File();
                    $file->name = '__init__.py';
                    $file->content = '';
                    $file->version()->associate($version);
                    $file->save();
                }
            }
        );

        static::saving(
            function($project) {
                $project->slug = Str::slug($project->name, '_');
                if (self::isForbidden($project->slug)) {
                    throw new \Exception('reserved name');
                }
            }
        );
    }

    /**
     * Get the User that owns the Project.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the Category this Project belongs to.
     *
     * @return BelongsTo
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the Versions this Project has.
     *
     * @return HasMany
     */
    public function versions(): HasMany
    {
        return $this->hasMany(Version::class);
    }

    /**
     * Get the Votes this Project has.
     *
     * @return HasMany
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * Get the Warnings for the Project.
     *
     * @return HasMany
     */
    public function warnings(): HasMany
    {
        return $this->hasMany(Warning::class);
    }

    /**
     * Get the BadgeProjects for the Project.
     * This contains support state per badge.
     *
     * @return HasMany
     */
    public function states(): HasMany
    {
        return $this->hasMany(BadgeProject::class);
    }

    /**
     * Collaborators.
     *
     * @return BelongsToMany
     */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Collaborator teams.
     *
     * @return BelongsToMany
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    /**
     * @return string
     */
    public function getRevisionAttribute(): ?string
    {
        $version = $this->versions()->published()->get()->last();

        return $version === null ? null : (string) $version->revision;
    }

    /**
     * @return BelongsToMany
     */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'dependencies', 'project_id', 'depends_on_project_id')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany
     */
    public function dependants(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'dependencies', 'depends_on_project_id', 'project_id')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany
     */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class)->withTimestamps();
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return Version
     */
    public function getUnpublishedVersion(): Version
    {
	/** @var Version|null $version */
        $version = $this->versions()->unPublished()->first();
        if ($version === null) {
            /** @var Version $previousVersion */
            $previousVersion = $this->versions->last();
            $revision = $previousVersion->revision + 1;
            $version = new Version();
            $version->user_id = $this->user_id;
            $version->revision = $revision;
            $version->project()->associate($this);
            $version->save();
        }

        return $version;
    }
}
