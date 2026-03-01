<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * GradeResource – Standardized JSON transformation for Grade model instances
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Consistent, predictable JSON shape for all grade-related API responses
 * • Includes essential fields + computed values (e.g., range display, is_used flag)
 * • Eager-loads & transforms related data (schoolSections collection)
 * • Handles both single Grade and Grade collections (via ::collection())
 * • Exposes only safe/public data — no sensitive internal fields leaked
 * • Supports conditional inclusion of relations (e.g., when loaded)
 * • Future-proof: easy to version (v1, v2) or add fields like weight/gpa_points later
 * • Improves frontend consumption (Inertia props + JSON endpoints)
 * • Reduces controller bloat — no manual array mapping needed
 *
 * How it fits into the Grades Module:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Used in GradeController (index, store, update, show) for JSON responses
 * • Returned when request wantsJson() → e.g. from DataTable AJAX or API calls
 * • Inertia pages can receive it as prop (e.g. 'grade' => new GradeResource($grade))
 * • Enables mobile apps / third-party integrations to consume the same endpoint
 * • Works seamlessly with the many-to-many schoolSections relationship
 * • Pairs well with Grade model accessors (range, isUsed) for derived data
 *
 * Usage Examples:
 *   return GradeResource::collection($grades);               // index
 *   return new GradeResource($grade->load('schoolSections')); // show/update
 *   return response()->json(['data' => new GradeResource($grade)]);
 *
 * Response Shape Example (single):
 * {
 *   "id": "uuid-string",
 *   "name": "Excellent",
 *   "code": "A",
 *   "min_score": 80,
 *   "max_score": 100,
 *   "range": "80 – 100",
 *   "remark": "Outstanding performance",
 *   "is_used": true,
 *   "school_sections": [
 *     { "id": "uuid", "name": "SS1 Science" },
 *     { "id": "uuid", "name": "SS2 Commercial" }
 *   ],
 *   "created_at": "2025-02-01T10:00:00.000000Z",
 *   "updated_at": "2025-02-15T14:30:00.000000Z"
 * }
 */
class GradeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'min_score' => (int) $this->min_score,
            'max_score' => (int) $this->max_score,

            // Computed / helper fields from model accessors
            'range' => $this->range,           // "80 – 100"
            'remark' => $this->remark,
            'is_used' => $this->isUsed(),        // from model helper

            // Relations – only included if loaded
            'school_sections' => $this->whenLoaded('schoolSections', function () {
                return $this->schoolSections->map(function ($section) {
                    return [
                        'id' => $section->id,
                        'name' => $section->name,
                        // Add more section fields if needed (e.g. level, arm)
                    ];
                });
            }),

            // Timestamps – ISO format for JS Date parsing
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Optional: add deleted_at if you want to expose soft-delete status
            // 'deleted_at'  => $this->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
     * @return void
     */
    public function withResponse($request, $response)
    {
        // Optional: add meta or links if building full JSON:API style
        // $response->header('X-Grade-Count', $this->resource->count() ?? 1);
    }
}
