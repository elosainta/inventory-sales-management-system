<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, LogsActivity;

    public const ROLE_OWNER        = 'owner';
    public const ROLE_HEAD_CHEF    = 'head_chef';
    public const ROLE_JUNIOR_CHEF  = 'junior_chef';
    public const ROLE_ADMIN        = 'admin';

    /**
     * Casual kitchen help. Sees prep, stock-take, wastage and inventory and
     * nothing else — no leave, no peer feedback, no tally, no production, and
     * none of the priced pages a junior chef is also kept off.
     *
     * Gates that read `! isAdmin()` or plain `true` hand a new role access by
     * default, which is how this one could quietly inherit half the app. Every
     * such gate was revisited when it was added, and PartTimerAccessTest holds
     * the whole matrix so the next role change has to face it.
     */
    public const ROLE_PART_TIMER   = 'part_timer';

    /**
     * Accounts granted a module their role does not carry.
     *
     * Hard-coded on the Owner's instruction: the per-user permission system was
     * removed (the user_permissions table survives but nothing reads it), so
     * there is no per-person switch left to use. Keyed on id AND name so that
     * it fails CLOSED — if id 13 is ever reused by a different person, the name
     * will not match and they get nothing, rather than silently inheriting the
     * right to edit recipes and every plate cost that depends on them.
     *
     * Emails are deliberately not used here: CLAUDE.md keeps staff addresses
     * out of the repo.
     */
    public const RECIPE_EXCEPTIONS = [13 => 'Riley Chen'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_demo',
        'preferred_language',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'is_demo'           => 'boolean',
        ];
    }

    /**
     * Owners and Head Chefs share full management access.
     * Use this in Policies to allow either of them.
     */
    public function isManager(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_HEAD_CHEF]);
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isHeadChef(): bool
    {
        return $this->role === self::ROLE_HEAD_CHEF;
    }

    public function isJuniorChef(): bool
    {
        return $this->role === self::ROLE_JUNIOR_CHEF;
    }

    /**
     * Where this account lives: the page login lands on, what the bare domain
     * redirects to, and where /dashboard sends anyone who has no dashboard.
     *
     * Written here because it was written three times and they disagreed.
     * DashboardController knew to bounce junior chefs and admins and nobody
     * else, so the part timer role added in 1.11.5 inherited "go to the
     * dashboard and get a 403" — on the bare domain, and on the sidebar logo,
     * which points at route('dashboard') from every page. It cost user 18
     * three refusals on 2026-09-03 before anyone looked at the access log.
     *
     * The default is the prep checklist rather than a money page, so a role
     * added tomorrow lands somewhere it can open instead of on a refusal:
     * view-checklist is open to every kitchen account.
     */
    public function homeRoute(): string
    {
        return match (true) {
            $this->isAdmin()   => 'support-tickets.index',
            $this->isManager() => 'dashboard',
            default            => 'prep.index',
        };
    }

    public function isPartTimer(): bool
    {
        return $this->role === self::ROLE_PART_TIMER;
    }

    /** Riley edits recipes despite being a junior chef. See RECIPE_EXCEPTIONS. */
    public function hasRecipeException(): bool
    {
        // The id guard is not redundant. On an unsaved User both sides are
        // null, and `null === null` grants the exception — which is how the
        // vault's gates matrix came to show every junior chef holding
        // manage-recipes: it evaluates gates against `new User([...])`. No
        // persisted account can reach that shape, so nothing was ever wrong
        // live, but an authorization check whose default is "yes" is the wrong
        // default even when unreachable.
        return $this->id !== null
            && (self::RECIPE_EXCEPTIONS[$this->id] ?? null) === $this->name;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * A hidden, owner-level testing account whose writes are blocked and whose
     * sales visibility is suppressed. Visible only to Admins.
     */
    public function isDemo(): bool
    {
        return (bool) $this->is_demo;
    }
}