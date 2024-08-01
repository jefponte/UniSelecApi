<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use EloquentFilter\Filterable;
use ReflectionClass;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Carbon\Carbon;

class ApplicationController extends BasicCrudController
{
    private $rules = [
        'user_id' => 'required|integer',
        'data' => 'required|array',
    ];

    /**
     * @OA\Get(
     *     path="/api/applications",
     *     summary="Get list of applications",
     *     tags={"Application"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Application"))
     *     )
     * )
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = (int) $request->get('per_page', $this->defaultPerPage);
        $hasFilter = in_array(Filterable::class, class_uses($this->model()));

        $query = $this->queryBuilder();

        if ($hasFilter) {
            $query = $query->filter($request->all());
        }


        if (!$user->can('admin')) {
            $query->where('user_id', $user->id);
        }

        $data = $query->orderBy('id', 'desc')->paginate($perPage);

        if ($data instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            return ApplicationResource::collection($data->items())->additional([
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                ],
            ]);
        }

        return ApplicationResource::collection($data);
    }

    /**
     * @OA\Post(
     *     path="/api/applications",
     *     summary="Create or update an application",
     *     tags={"Application"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/Application")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Application created or updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Application")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {

        $start = Carbon::create(2024, 7, 2, 8, 0, 0);
        $end = Carbon::create(2024, 8, 3, 23, 59, 0);
        $now = now();

        if ($now->lt($start) || $now->gt($end)) {
            return response()->json([
                'message' => 'Inscrições estão fechadas. O período de inscrição é de 02/08/2024 a 03/08/2024.',
            ], 403);
        }

        $userId = $request->user()->id;

        $existingApplication = Application::where('user_id', $userId)->first();

        $applicationData = $request->all();
        $applicationData['user_id'] = $userId;


        $currentTimestamp = now()->toDateTimeString();
        if (!isset($applicationData['data'])) {
            $applicationData['data'] = [];
        }
        $applicationData['data']['updated_at'] = $currentTimestamp;

        if (isset($request->data)) {
            $applicationData['verification_code']  = md5(json_encode($applicationData['data']));
        }

        if ($existingApplication) {
            $existingApplication->update($applicationData);
            return response()->json([
                'message' => 'Inscrição atualizada com sucesso.',
                'application' => $existingApplication
            ], 200);
        }

        $request->merge(['user_id' => $userId]);
        $application = Application::create($applicationData);

        return response()->json([
            'message' => 'Inscrição criada com sucesso.',
            'application' => $application
        ], 201);
    }



    /**
     * @OA\Get(
     *     path="/api/applications/{id}",
     *     summary="Get an application by ID",
     *     tags={"Application"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Application")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Application not found or not authorized"
     *     )
     * )
     */
    public function show($id)
    {

        $userId = request()->user()->id;
        $application = $this->model()::where('id', $id)
            ->where('user_id', $userId)
            ->first();
        if (!$application) {
            return response()->json(['message' => 'Application not found or not authorized'], 404);
        }
        return new ApplicationResource($application);
    }

    /**
     * @OA\Put(
     *     path="/api/applications/{id}",
     *     summary="Update an application",
     *     tags={"Application"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/Application")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Application updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Application")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Application not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    /**
     * Método `destroy` removido conforme solicitado
     */
    public function destroy($id)
    {
        return response()->json(['error' => 'Method not allowed.'], 405);
    }

    protected function model()
    {
        return Application::class;
    }

    protected function rulesStore()
    {
        return $this->rules;
    }

    protected function rulesUpdate()
    {
        return $this->rules;
    }

    protected function resourceCollection()
    {
        return ApplicationResource::collection($this->model()::all());
    }

    protected function resource()
    {
        return ApplicationResource::class;
    }
}
