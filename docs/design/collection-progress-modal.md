# Collection Progress Modal

## Problem

When clicking "Collect Data" on `/admin/industry/{slug}`, the UI freezes for ~108 seconds while data collection runs synchronously. Users have no visibility into progress and cannot cancel the operation.

## Solution

Replace the synchronous form submission with an AJAX-based modal that:
1. Shows real-time progress updates during collection
2. Provides a Cancel button to abort the process

Collection remains synchronous (no job queue changes required).

---

## Status Information to Display

The existing `/collection-run/status?id={id}` endpoint provides these fields:

| Field | UI Display | Update Frequency |
|-------|------------|------------------|
| `status` | Badge: Running / Complete / Failed / Cancelled | Real-time |
| `companies_total` | "Collecting X companies" | Once at start |
| `companies_success` | Progress bar + "Y of X complete" | Per batch (~10 companies) |
| `companies_failed` | Red counter if > 0 | Per batch |
| `duration_seconds` | Elapsed time display | Every poll |
| `gate_passed` | Final result indicator | On completion |
| `error_count` / `warning_count` | Issue summary | On completion |

### Progress Phases (Display Sequence)

1. **Initializing** - "Creating collection run..."
2. **Collecting Macro Data** - "Fetching macro indicators..."
3. **Collecting Company Data** - "Collecting company Y of X..." with progress bar
4. **Validating** - "Running gate validation..."
5. **Complete** / **Cancelled** / **Failed** - Final status with summary

---

## Modal UI Design

Follow existing modal pattern from `yii/src/views/industry/view.php` (Add Companies modal).

### HTML Structure

```html
<div id="collect-modal" class="modal modal--hidden">
    <div class="modal__backdrop"></div>
    <div class="modal__content modal__content--narrow">
        <div class="modal__header">
            <h3 class="modal__title">Data Collection</h3>
            <!-- No close X button - must use Cancel or wait for completion -->
        </div>
        <div class="modal__body">
            <!-- Phase indicator -->
            <div class="collect-phase">
                <span class="collect-phase__label">Collecting company data...</span>
            </div>

            <!-- Progress bar -->
            <div class="progress-bar">
                <div class="progress-bar__fill" style="width: 45%"></div>
            </div>
            <div class="progress-bar__text">
                <span class="progress-bar__count">9 of 20 companies</span>
                <span class="progress-bar__time">Elapsed: 45s</span>
            </div>

            <!-- Status indicators (shown after completion) -->
            <div class="collect-result collect-result--hidden">
                <div class="collect-result__status">
                    <span class="badge badge--success">Complete</span>
                </div>
                <div class="collect-result__summary">
                    <div>Companies: 19 success, 1 failed</div>
                    <div>Gate: Passed</div>
                    <div>Duration: 108s</div>
                </div>
            </div>
        </div>
        <div class="modal__footer">
            <!-- During collection -->
            <button type="button" class="btn btn--danger" id="cancel-collect-btn">
                Cancel
            </button>

            <!-- After completion (replaces Cancel) -->
            <a href="/collection-run/view?id=123" class="btn btn--primary btn--hidden" id="view-run-btn">
                View Results
            </a>
            <button type="button" class="btn btn--secondary btn--hidden" id="close-collect-btn">
                Close
            </button>
        </div>
    </div>
</div>
```

### CSS Classes (add to `admin.css`)

```css
/* Progress Modal */
.modal__content--narrow {
    max-width: 420px;
}

.collect-phase {
    text-align: center;
    margin-bottom: var(--space-4);
}

.collect-phase__label {
    color: var(--text-secondary);
    font-size: var(--text-sm);
}

.progress-bar {
    height: 8px;
    background: var(--bg-muted);
    border-radius: var(--radius-sm);
    overflow: hidden;
}

.progress-bar__fill {
    height: 100%;
    background: var(--brand-primary);
    transition: width 0.3s ease;
}

.progress-bar__text {
    display: flex;
    justify-content: space-between;
    margin-top: var(--space-2);
    font-size: var(--text-sm);
    color: var(--text-secondary);
}

.collect-result {
    text-align: center;
    padding: var(--space-4) 0;
}

.collect-result--hidden {
    display: none;
}

.collect-result__status {
    margin-bottom: var(--space-3);
}

.collect-result__summary {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    line-height: 1.6;
}

.btn--hidden {
    display: none;
}
```

---

## Cancel Mechanism

### Database Changes

Add `cancel_requested` column to `collection_run` table:

```sql
ALTER TABLE collection_run
ADD COLUMN cancel_requested TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
```

Add new status constant:

```php
// In CollectionRun model
public const STATUS_CANCELLED = 'cancelled';
```

### Backend Flow

1. **Cancel Endpoint** (`CollectionRunController`):
```php
public function actionCancel(int $id): Response
{
    $run = CollectionRun::findOne($id);
    if ($run && $run->status === CollectionRun::STATUS_RUNNING) {
        $run->cancel_requested = 1;
        $run->save(false, ['cancel_requested']);
        return $this->asJson(['success' => true]);
    }
    return $this->asJson(['success' => false, 'error' => 'Cannot cancel']);
}
```

2. **Check in Handler** (`CollectIndustryHandler`):
```php
// After each batch, check for cancellation
private function isCancellationRequested(int $runId): bool
{
    return (bool) CollectionRun::find()
        ->where(['id' => $runId, 'cancel_requested' => 1])
        ->exists();
}

// In collection loop:
foreach ($batches as $batch) {
    // Process batch...

    if ($this->isCancellationRequested($runId)) {
        $this->markCancelled($run);
        return $this->buildCancelledResult($run);
    }
}
```

3. **Mark Cancelled** (add to `CollectionRun` model):
```php
public function markCancelled(): void
{
    $this->status = self::STATUS_CANCELLED;
    $this->completed_at = date('Y-m-d H:i:s');
    $this->save(false, ['status', 'completed_at']);
}
```

### Frontend Flow

1. Click "Collect Data" button
2. JavaScript intercepts form submit, shows modal
3. AJAX POST to `/industry/collect/{slug}` (modified to return JSON)
4. Start polling `/collection-run/status?id={runId}` every 2 seconds
5. Update progress bar and phase text on each poll
6. If Cancel clicked: POST to `/collection-run/cancel?id={runId}`
7. On completion/cancellation: show result, swap buttons

---

## Implementation Steps

### Step 1: Database Migration

Create migration `m260111_000000_add_cancel_requested_to_collection_run.php`:

```php
public function safeUp()
{
    $this->addColumn('{{%collection_run}}', 'cancel_requested', $this->boolean()->notNull()->defaultValue(0)->after('status'));
}

public function safeDown()
{
    $this->dropColumn('{{%collection_run}}', 'cancel_requested');
}
```

### Step 2: Update CollectionRun Model

1. Add property `@property bool $cancel_requested`
2. Add `STATUS_CANCELLED = 'cancelled'` constant
3. Update `status` validation to include `cancelled`
4. Add `markCancelled()` method
5. Add `cancel_requested` to rules

### Step 3: Update CollectIndustryHandler

File: `yii/src/handlers/collection/CollectIndustryHandler.php`

1. Add `isCancellationRequested(int $runId): bool` method
2. In company batch loop, check cancellation after each batch
3. If cancelled, call `markCancelled()` and return early with cancelled result
4. Update result DTO to include cancelled state

### Step 4: Add Cancel Endpoint

File: `yii/src/controllers/CollectionRunController.php`

Add `actionCancel(int $id)`:
- Validate run exists and is running
- Set `cancel_requested = 1`
- Return JSON response

### Step 5: Modify Collect Action for AJAX

File: `yii/src/controllers/IndustryController.php`

Update `actionCollect()`:
- If request is AJAX, return JSON with `runId`
- If not AJAX, keep existing redirect behavior

```php
if (Yii::$app->request->isAjax) {
    return $this->asJson([
        'success' => $result->success,
        'runId' => $result->runId,
        'errors' => $result->errors,
    ]);
}
// Existing redirect logic...
```

### Step 6: Update Status Endpoint

File: `yii/src/controllers/CollectionRunController.php`

Update `actionStatus()` to include:
- `cancel_requested` field
- Current phase hint (based on progress)

### Step 7: Add Modal HTML

File: `yii/src/views/industry/view.php`

Add modal HTML after existing "Add Members" modal (see HTML structure above).

### Step 8: Add CSS

File: `yii/web/css/admin.css`

Add progress bar and modal modifier styles (see CSS above).

### Step 9: Add JavaScript

File: `yii/src/views/industry/view.php` (inline script)

```javascript
const collectBtn = document.querySelector('button[type="submit"][class*="btn--primary"]');
const collectForm = collectBtn?.closest('form');
const modal = document.getElementById('collect-modal');

let pollInterval = null;
let currentRunId = null;

collectForm?.addEventListener('submit', function(e) {
    e.preventDefault();
    startCollection();
});

async function startCollection() {
    showModal();
    updatePhase('Initializing collection...');

    const response = await fetch(collectForm.action, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': document.querySelector('[name="<?= Yii::$app->request->csrfParam ?>"]').value
        }
    });

    const data = await response.json();

    if (data.success && data.runId) {
        currentRunId = data.runId;
        startPolling();
    } else {
        showError(data.errors?.join(', ') || 'Collection failed to start');
    }
}

function startPolling() {
    pollInterval = setInterval(pollStatus, 2000);
}

async function pollStatus() {
    const response = await fetch(`/collection-run/status?id=${currentRunId}`);
    const status = await response.json();

    updateProgress(status);

    if (['complete', 'failed', 'cancelled'].includes(status.status)) {
        clearInterval(pollInterval);
        showResult(status);
    }
}

async function cancelCollection() {
    if (!confirm('Cancel data collection? Progress will be lost.')) return;

    await fetch(`/collection-run/cancel?id=${currentRunId}`, { method: 'POST' });
    updatePhase('Cancelling...');
}

function updateProgress(status) {
    const total = status.companies_total || 1;
    const done = status.companies_success + status.companies_failed;
    const pct = Math.round((done / total) * 100);

    document.querySelector('.progress-bar__fill').style.width = pct + '%';
    document.querySelector('.progress-bar__count').textContent = `${done} of ${total} companies`;
    document.querySelector('.progress-bar__time').textContent = `Elapsed: ${status.duration_seconds || 0}s`;

    if (done < total) {
        updatePhase(`Collecting company ${done + 1} of ${total}...`);
    } else {
        updatePhase('Validating...');
    }
}

function updatePhase(text) {
    document.querySelector('.collect-phase__label').textContent = text;
}

function showResult(status) {
    // Hide progress, show result
    document.querySelector('.collect-result').classList.remove('collect-result--hidden');
    document.getElementById('cancel-collect-btn').classList.add('btn--hidden');
    document.getElementById('view-run-btn').href = `/collection-run/view?id=${currentRunId}`;
    document.getElementById('view-run-btn').classList.remove('btn--hidden');
    document.getElementById('close-collect-btn').classList.remove('btn--hidden');

    // Set status badge
    const badge = document.querySelector('.collect-result__status .badge');
    badge.textContent = status.status.charAt(0).toUpperCase() + status.status.slice(1);
    badge.className = 'badge badge--' + (status.status === 'complete' && status.gate_passed ? 'success' : 'danger');

    // Set summary
    const summary = document.querySelector('.collect-result__summary');
    summary.innerHTML = `
        <div>Companies: ${status.companies_success} success, ${status.companies_failed} failed</div>
        <div>Gate: ${status.gate_passed ? 'Passed' : 'Failed'}</div>
        <div>Duration: ${status.duration_seconds}s</div>
    `;
}

function showModal() {
    modal.classList.remove('modal--hidden');
}

function closeModal() {
    modal.classList.add('modal--hidden');
    location.reload(); // Refresh to show updated run list
}

document.getElementById('cancel-collect-btn')?.addEventListener('click', cancelCollection);
document.getElementById('close-collect-btn')?.addEventListener('click', closeModal);
```

---

## Verification Checklist

- [ ] Migration runs successfully
- [ ] "Collect Data" opens modal instead of freezing UI
- [ ] Progress bar updates every 2 seconds
- [ ] Company count increments as batches complete
- [ ] Elapsed time displays correctly
- [ ] Cancel button stops collection within one batch cycle (~10-15 seconds)
- [ ] Cancelled run shows correct status in run list
- [ ] Completed run shows "View Results" button
- [ ] Modal cannot be closed during active collection (no X button, no backdrop click)
- [ ] ESC key does not close modal during collection
- [ ] Works in both light and dark mode

---

## Files to Modify

| File | Changes |
|------|---------|
| `yii/migrations/m260111_*_add_cancel_requested.php` | New migration |
| `yii/src/models/CollectionRun.php` | Add column, status, methods |
| `yii/src/handlers/collection/CollectIndustryHandler.php` | Check cancellation |
| `yii/src/controllers/IndustryController.php` | AJAX response in `actionCollect` |
| `yii/src/controllers/CollectionRunController.php` | Add `actionCancel`, update `actionStatus` |
| `yii/src/views/industry/view.php` | Add modal HTML + JavaScript |
| `yii/web/css/admin.css` | Add progress bar styles |

---

## Future Enhancements (Out of Scope)

- WebSocket for instant updates (eliminates polling)
- Per-company status display (show which ticker is being collected)
- Retry failed companies from modal
- Background job queue for true async collection
