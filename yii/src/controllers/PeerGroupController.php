<?php

declare(strict_types=1);

namespace app\controllers;

use app\dto\peergroup\AddMembersRequest;
use app\dto\peergroup\CollectPeerGroupRequest;
use app\dto\peergroup\CreatePeerGroupRequest;
use app\dto\peergroup\RemoveMemberRequest;
use app\dto\peergroup\SetFocalRequest;
use app\dto\peergroup\TogglePeerGroupRequest;
use app\dto\peergroup\UpdatePeerGroupRequest;
use app\filters\AdminAuthFilter;
use app\handlers\peergroup\AddMembersInterface;
use app\handlers\peergroup\CollectPeerGroupInterface;
use app\handlers\peergroup\CreatePeerGroupInterface;
use app\handlers\peergroup\RemoveMemberInterface;
use app\handlers\peergroup\SetFocalInterface;
use app\handlers\peergroup\TogglePeerGroupInterface;
use app\handlers\peergroup\UpdatePeerGroupInterface;
use app\queries\CollectionPolicyQuery;
use app\queries\CollectionRunRepository;
use app\queries\PeerGroupListQuery;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller for managing peer groups.
 *
 * Provides CRUD operations for the admin UI.
 * All actions require HTTP Basic Authentication.
 */
final class PeerGroupController extends Controller
{
    public $layout = 'main';

    public function __construct(
        $id,
        $module,
        private readonly PeerGroupListQuery $listQuery,
        private readonly PeerGroupQuery $peerGroupQuery,
        private readonly PeerGroupMemberQuery $memberQuery,
        private readonly CollectionPolicyQuery $policyQuery,
        private readonly CollectionRunRepository $runRepository,
        private readonly CreatePeerGroupInterface $createHandler,
        private readonly UpdatePeerGroupInterface $updateHandler,
        private readonly TogglePeerGroupInterface $toggleHandler,
        private readonly AddMembersInterface $addMembersHandler,
        private readonly RemoveMemberInterface $removeMemberHandler,
        private readonly SetFocalInterface $setFocalHandler,
        private readonly CollectPeerGroupInterface $collectHandler,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return [
            'auth' => [
                'class' => AdminAuthFilter::class,
            ],
        ];
    }

    /**
     * List all peer groups.
     */
    public function actionIndex(): string
    {
        $request = Yii::$app->request;

        $sector = $request->get('sector');
        $sector = is_string($sector) && $sector !== '' ? $sector : null;

        $isActive = $request->get('status');
        if ($isActive === 'active') {
            $isActive = true;
        } elseif ($isActive === 'inactive') {
            $isActive = false;
        } else {
            $isActive = null;
        }

        $search = $request->get('search');
        $orderBy = (string) $request->get('order', 'name');
        $orderDirection = (string) $request->get('dir', 'ASC');

        $list = $this->listQuery->list(
            $sector,
            $isActive,
            is_string($search) ? $search : null,
            $orderBy,
            $orderDirection
        );

        return $this->render('index', [
            'groups' => $list->groups,
            'counts' => $list->counts,
            'sectors' => $this->listQuery->getSectors(),
            'currentSector' => $sector,
            'currentStatus' => $request->get('status'),
            'currentSearch' => $search,
            'currentOrder' => $orderBy,
            'currentDir' => $orderDirection,
        ]);
    }

    /**
     * View a single peer group with members.
     */
    public function actionView(string $slug): string
    {
        $group = $this->listQuery->findBySlug($slug);

        if ($group === null) {
            throw new NotFoundHttpException('Peer group not found.');
        }

        $members = $this->memberQuery->findByGroup($group->id);
        $runs = $this->runRepository->listByPeerGroup($group->id);

        return $this->render('view', [
            'group' => $group,
            'members' => $members,
            'runs' => $runs,
        ]);
    }

    /**
     * Create a new peer group.
     */
    public function actionCreate(): Response|string
    {
        $request = Yii::$app->request;
        $policies = $this->policyQuery->findAll();

        if ($request->isPost) {
            $name = trim((string) $request->post('name', ''));
            $slug = trim((string) $request->post('slug', ''));
            $sector = trim((string) $request->post('sector', ''));
            $description = trim((string) $request->post('description', ''));
            $policyId = $request->post('policy_id');

            // Auto-generate slug from name if not provided
            if ($slug === '' && $name !== '') {
                $slug = $this->generateSlug($name);
            }

            $result = $this->createHandler->create(new CreatePeerGroupRequest(
                name: $name,
                slug: $slug,
                sector: $sector,
                actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
                description: $description !== '' ? $description : null,
                policyId: $policyId !== null && $policyId !== '' ? (int) $policyId : null,
                isActive: true,
            ));

            if ($result->success) {
                Yii::$app->session->setFlash('success', 'Peer group created successfully.');
                return $this->redirect(['view', 'slug' => $result->group->slug]);
            }

            return $this->render('create', [
                'name' => $name,
                'slug' => $slug,
                'sector' => $sector,
                'description' => $description,
                'policyId' => $policyId,
                'policies' => $policies,
                'errors' => $result->errors,
            ]);
        }

        return $this->render('create', [
            'name' => '',
            'slug' => '',
            'sector' => '',
            'description' => '',
            'policyId' => null,
            'policies' => $policies,
            'errors' => [],
        ]);
    }

    /**
     * Update an existing peer group.
     */
    public function actionUpdate(string $slug): Response|string
    {
        $group = $this->listQuery->findBySlug($slug);

        if ($group === null) {
            throw new NotFoundHttpException('Peer group not found.');
        }

        $request = Yii::$app->request;
        $policies = $this->policyQuery->findAll();

        if ($request->isPost) {
            $name = trim((string) $request->post('name', ''));
            $description = trim((string) $request->post('description', ''));
            $policyId = $request->post('policy_id');

            $result = $this->updateHandler->update(new UpdatePeerGroupRequest(
                id: $group->id,
                name: $name,
                actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
                description: $description !== '' ? $description : null,
                policyId: $policyId !== null && $policyId !== '' ? (int) $policyId : null,
            ));

            if ($result->success) {
                Yii::$app->session->setFlash('success', 'Peer group updated successfully.');
                return $this->redirect(['view', 'slug' => $slug]);
            }

            return $this->render('update', [
                'group' => $group,
                'name' => $name,
                'description' => $description,
                'policyId' => $policyId,
                'policies' => $policies,
                'errors' => $result->errors,
            ]);
        }

        return $this->render('update', [
            'group' => $group,
            'name' => $group->name,
            'description' => $group->description ?? '',
            'policyId' => $group->policyId,
            'policies' => $policies,
            'errors' => [],
        ]);
    }

    /**
     * Toggle active status (POST only).
     */
    public function actionToggle(string $slug): Response
    {
        $request = Yii::$app->request;

        if (!$request->isPost) {
            throw new NotFoundHttpException('Method not allowed.');
        }

        $group = $this->listQuery->findBySlug($slug);

        if ($group === null) {
            throw new NotFoundHttpException('Peer group not found.');
        }

        $result = $this->toggleHandler->toggle(new TogglePeerGroupRequest(
            id: $group->id,
            isActive: !$group->isActive,
            actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
        ));

        if ($result->success) {
            $status = $result->group->isActive ? 'activated' : 'deactivated';
            Yii::$app->session->setFlash('success', "Peer group {$status} successfully.");
        } else {
            Yii::$app->session->setFlash('error', implode(' ', $result->errors));
        }

        $returnUrl = $request->post('return_url', ['index']);
        return $this->redirect($returnUrl);
    }

    /**
     * Add members to a peer group (POST only).
     */
    public function actionAddMembers(string $slug): Response
    {
        $request = Yii::$app->request;

        if (!$request->isPost) {
            throw new NotFoundHttpException('Method not allowed.');
        }

        $group = $this->listQuery->findBySlug($slug);

        if ($group === null) {
            throw new NotFoundHttpException('Peer group not found.');
        }

        $tickersInput = trim((string) $request->post('tickers', ''));
        $tickers = $this->parseTickers($tickersInput);

        if (empty($tickers)) {
            Yii::$app->session->setFlash('error', 'No valid tickers provided.');
            return $this->redirect(['view', 'slug' => $slug]);
        }

        $result = $this->addMembersHandler->add(new AddMembersRequest(
            groupId: $group->id,
            tickers: $tickers,
            actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
        ));

        if ($result->success) {
            $message = count($result->added) . ' member(s) added.';
            if (!empty($result->skipped)) {
                $message .= ' ' . count($result->skipped) . ' already existed.';
            }
            Yii::$app->session->setFlash('success', $message);
        } else {
            Yii::$app->session->setFlash('error', implode(' ', $result->errors));
        }

        return $this->redirect(['view', 'slug' => $slug]);
    }

    /**
     * Remove a member from a peer group (POST only).
     */
    public function actionRemoveMember(string $slug): Response
    {
        $request = Yii::$app->request;

        if (!$request->isPost) {
            throw new NotFoundHttpException('Method not allowed.');
        }

        $group = $this->listQuery->findBySlug($slug);

        if ($group === null) {
            throw new NotFoundHttpException('Peer group not found.');
        }

        $companyId = (int) $request->post('company_id', 0);

        if ($companyId <= 0) {
            Yii::$app->session->setFlash('error', 'Invalid company ID.');
            return $this->redirect(['view', 'slug' => $slug]);
        }

        $result = $this->removeMemberHandler->remove(new RemoveMemberRequest(
            groupId: $group->id,
            companyId: $companyId,
            actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
        ));

        if ($result->success) {
            Yii::$app->session->setFlash('success', 'Member removed successfully.');
        } else {
            Yii::$app->session->setFlash('error', implode(' ', $result->errors));
        }

        return $this->redirect(['view', 'slug' => $slug]);
    }

    /**
     * Set a focal company in a peer group (POST only).
     */
    public function actionSetFocal(string $slug): Response
    {
        $request = Yii::$app->request;

        if (!$request->isPost) {
            throw new NotFoundHttpException('Method not allowed.');
        }

        $group = $this->listQuery->findBySlug($slug);

        if ($group === null) {
            throw new NotFoundHttpException('Peer group not found.');
        }

        $companyId = (int) $request->post('company_id', 0);

        if ($companyId <= 0) {
            Yii::$app->session->setFlash('error', 'Invalid company ID.');
            return $this->redirect(['view', 'slug' => $slug]);
        }

        $result = $this->setFocalHandler->setFocal(new SetFocalRequest(
            groupId: $group->id,
            companyId: $companyId,
            actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
        ));

        if ($result->success) {
            Yii::$app->session->setFlash('success', 'Focal company updated successfully.');
        } else {
            Yii::$app->session->setFlash('error', implode(' ', $result->errors));
        }

        return $this->redirect(['view', 'slug' => $slug]);
    }

    /**
     * Trigger data collection for a peer group (POST only).
     */
    public function actionCollect(string $slug): Response
    {
        $request = Yii::$app->request;

        if (!$request->isPost) {
            throw new NotFoundHttpException('Method not allowed.');
        }

        $group = $this->listQuery->findBySlug($slug);

        if ($group === null) {
            throw new NotFoundHttpException('Peer group not found.');
        }

        $result = $this->collectHandler->collect(new CollectPeerGroupRequest(
            groupId: $group->id,
            actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
        ));

        if ($result->success) {
            Yii::$app->session->setFlash(
                'success',
                'Collection started. Run ID: ' . $result->runId
            );

            // Redirect to collection run view
            return $this->redirect(['collection-run/view', 'id' => $result->runId]);
        }

        Yii::$app->session->setFlash('error', implode(' ', $result->errors));
        return $this->redirect(['view', 'slug' => $slug]);
    }

    /**
     * Parse ticker input into an array of normalized tickers.
     *
     * @return string[]
     */
    private function parseTickers(string $input): array
    {
        // Split by newlines or commas
        $parts = preg_split('/[\n,]+/', $input);
        $tickers = [];

        foreach ($parts as $part) {
            $ticker = strtoupper(trim($part));
            // Only allow alphanumeric and period (for tickers like BRK.B)
            $ticker = preg_replace('/[^A-Z0-9.]/', '', $ticker);
            if ($ticker !== '') {
                $tickers[] = $ticker;
            }
        }

        return array_unique($tickers);
    }

    /**
     * Generate a URL-safe slug from a name.
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }
}
