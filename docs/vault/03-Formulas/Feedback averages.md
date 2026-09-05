# Feedback averages

Anonymous peer feedback. Three nested means, and the nesting is the interesting part.

## The instrument

Five fixed 1–5 questions, `app/Models/FeedbackEntry.php:16-22`:

```php
public const QUESTIONS = [
    'cleanliness'   => 'Cleanliness of their station',
    'safety'        => 'Food & equipment safety',
    'organisation'  => 'Organisation of the kitchen',
    'teamwork'      => 'Teamwork during service',
    'communication' => 'Communication',
];
```

Keys map to `rating_*` columns. Fixed set, no configurability — five columns rather than an EAV table.

## Three formulas

### 1 · Entry average — one rating of one person

$$\bar{r}_e = \operatorname{round}\!\left(\frac{1}{5}\sum_{k \in K} r_{e,k},\; 1\right)$$

`FeedbackEntry.php:60-63`

```php
public function averageRating(): float
{
    return round(collect(array_keys(self::QUESTIONS))->avg(fn ($key) => $this->rating($key)), 1);
}
```

All five questions weighted equally. Cleanliness counts exactly as much as safety.

### 2 · Question average — how the kitchen scores on one dimension

$$\bar{R}_k = \operatorname{round}\!\left(\frac{1}{N}\sum_{e \in E} r_{e,k},\; 1\right)$$

`FeedbackEntry.php:87-88`

```php
$questionAverages = collect(self::QUESTIONS)
    ->map(fn ($label, $key) => $entries->isEmpty() ? null : round($entries->avg(fn ($entry) => $entry->rating($key)), 1));
```

Across **every entry in the month**, regardless of who was rated. Answers "is the kitchen weak on communication?" — not "who is weak on communication?"

Empty month yields `null`, not `0`. A 1–5 scale has no valid 0, so 0 would read as a catastrophic score.

### 3 · Per-person average — **a mean of means**

$$\bar{P}_u = \operatorname{round}\!\left(\frac{1}{|E_u|}\sum_{e \in E_u} \bar{r}_e,\; 1\right)$$

`FeedbackEntry.php:90-97`

```php
$perPerson = $entries->groupBy('to_user_id')
    ->map(fn ($group) => [
        'name'    => $group->first()->toUser?->name ?? '—',
        'count'   => $group->count(),
        'average' => round($group->avg(fn ($entry) => $entry->averageRating()), 1),
    ])
    ->sortByDesc('average')
    ->values();
```

> [!note] This is the mean of already-rounded entry means, not the mean of raw ratings
> `averageRating()` rounds to 1 decimal *before* the outer average consumes it. Double rounding introduces up to ±0.05 of drift versus averaging all $5|E_u|$ raw values directly.
>
> Irrelevant on a 1–5 scale reported to one decimal — the drift is smaller than the reporting precision. Worth knowing only if the scale ever changes or someone starts ranking people by hundredths.

Because every entry contributes equally regardless of how many people rated whom, $\bar{P}$ is an unweighted mean over entries — one rater who submits three entries about the same person carries three times the weight of a rater who submits one.

## Worked example

Three entries about Chris in March:

| From | clean | safety | org | team | comm | $\bar{r}_e$ |
|---|---|---|---|---|---|---|
| A | 4 | 5 | 4 | 3 | 4 | $20/5 = 4.0$ |
| B | 5 | 5 | 4 | 4 | 5 | $23/5 = 4.6$ |
| C | 3 | 4 | 3 | 4 | 3 | $17/5 = 3.4$ |

$$\bar{P}_{\text{Chris}} = \operatorname{round}\!\left(\frac{4.0 + 4.6 + 3.4}{3},\ 1\right) = \operatorname{round}(4.0,\ 1) = \mathbf{4.0}$$

Question average for teamwork across these three entries:

$$\bar{R}_{\text{team}} = \frac{3 + 4 + 4}{3} = \mathbf{3.7}$$

The lowest of the five — the actionable finding, and the reason question averages are reported separately from person averages.

## The anonymity boundary

The formulas are trivial; the access control is not.

| Who | Sees |
|---|---|
| Recipient | each rating, labelled **"From a teammate"** — sender names are never passed into the staff view |
| Owner | who sent what, plus all three aggregates, plus a month-filtered PDF |
| Admin | nothing — `view-feedback` is `! isAdmin()` |

Gates:

```php
Gate::define('view-feedback',       fn (User $user) => ! $user->isAdmin());
Gate::define('submit-feedback',     fn (User $user) => ! $user->isAdmin() && ! $user->isOwner());
Gate::define('export-feedback-pdf', fn (User $user) => $user->isOwner());
```

The Owner **oversees but does not rate** — `submit-feedback` excludes owners. Feedback from the person who controls your job is not peer feedback.

> [!danger] Small-n de-anonymisation
> With three junior chefs, a recipient seeing three ratings can often infer who said what. No aggregation floor is enforced — the Owner's report shows a person's average even from a single entry. Worth knowing before treating anonymity as a strong guarantee.

## One shared query

`monthReport()` returns `[$entries, $monthLabel, $questionAverages, $perPerson]` and is called by **three** consumers: the Owner page, the PDF export, and the monthly email. Same pattern as [[Sale revenue]] — one definition, several callers, no possible drift.

Monthly email: `feedback:monthly-report`, last day of month at 23:30, skips silently if there are no entries.

## Attachments

Streamed to the private disk. Downloads restricted to sender, recipient, or Owner.

## Predecessor

Replaced the Compliance module. `ComplianceReport` and the `compliance_reports` table are **kept** so historical data survives and stays visible in the [[Audit trail]]; the controller, views, PDF, command and gates were deleted.

## See also

[[Roles]] · [[Authorization gates]] · [[Audit trail]] · [[Formulas index]]
