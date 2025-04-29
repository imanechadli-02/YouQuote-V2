<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Like;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LikeController extends Controller
{
    /** ********************************************************************************************************************
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();

        $mylikes = Like::where('user_id', $user->id)
            ->with(['quote.tags', 'quote.categories', 'quote.user'])
            ->get();

        $formattedMylikes = $mylikes->map(function ($like) {
            if (! $like->quote) {
                return null;
            }

            return [
                "citation" => [
                    "id"         => $like->quote->id,
                    "content"    => $like->quote->content,
                    "user_id"    => $like->quote->user->name,
                    "popularite" => $like->quote->popularite,
                    "tags"       => $like->quote->tags->pluck('name'),
                    "categories" => $like->quote->categories->pluck('name'),
                ],
            ];
        });

        return response()->json($formattedMylikes);
    }

    /** ********************************************************************************************************************
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'quote_id' => [
                'required',
                'integer',
                'exists:quotes,id',
                Rule::unique('likes')->where(function ($query) use ($user) {
                    return $query->where('user_id', $user->id);
                }),
            ],
        ], [
            'quote_id.unique' => 'Vous avez déjà aimé cette citation.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "error" => $validator->errors()->first(),
            ], 400);
        }

        $citation = Quote::withTrashed()->find($request->quote_id);

        if (! $citation) {
            return response()->json([
                "error" => "Cette citation n'existe pas.",
            ], 404);
        }

        if ($citation->deleted_at !== null) {
            return response()->json([
                "error" => "Cette citation a été supprimée.",
            ], 410);
        }

        $like = Like::create([
            'quote_id' => $request->quote_id,
            'user_id'  => $user->id,
        ]);

        $like->load(['quote.tags', 'quote.categories', 'quote.user']);

        return response()->json([
            "success"  => true,
            "message"  => "Vous avez aimé une citation.",
            "citation" => [
                "id"         => $like->quote->id,
                "content"    => $like->quote->content,
                "user_id"    => $like->quote->user->name,
                "popularite" => $like->quote->popularite,
                "tags"       => $like->quote->tags->pluck('name'),
                "categories" => $like->quote->categories->pluck('name'),
            ],
        ], 201);
    }

    /** ********************************************************************************************************************
     * Display the specified resource.
     */
    public function show(string $id)
    {

        $user     = auth()->user();
        $citation = Quote::findOrFail($id);

        if (! $user->hasRole('Admin') && $user->id !== $citation->user_id) {
            return response()->json(['message' => 'Accès refusé, Vouz n\'avez pas l\'accès de voir les likes des quotes des autres auteurs'], 403);
        }

        $likes = Like::where('quote_id', $id)
            ->with(['user', 'quote'])
            ->get();

        if ($likes->isEmpty()) {
            return response()->json([
                'message' => 'Aucun like trouvé pour cette citation.',
            ], 404);
        }

        $formattedLikes = $likes->map(function ($like) {
            return [
                // 'quote' => $like->quote->content,
                'user aimé' => $like->user->name . '  -  ' . $like->user->email,
            ];
        });

        return response()->json([
            'quote' => $citation->content,
            'likes' => $formattedLikes,
        ], 200);
    }

    /** ********************************************************************************************************************
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /** ********************************************************************************************************************
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = auth()->user();
        $like = Like::where('user_id', $user->id)->where('quote_id', $id)->first();
        $like->delete();
        return response()->json([
            "success" => true,
            "message" => "Vous avez retiré un like sur un citation.",
        ], 200);

    }
}
