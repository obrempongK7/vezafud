<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Models\RecipeLike;
use App\Models\SavedRecipe;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    public function index(Request $request)
    {
        $q = Recipe::with(['user.profile','category'])->where('status','published')->where('visibility','public');
        if ($request->category) $q->whereHas('category',fn($c)=>$c->where('slug',$request->category));
        if ($request->difficulty) $q->where('difficulty',$request->difficulty);
        if ($request->search) $q->where('title','like',"%{$request->search}%");
        if ($request->cuisine) $q->whereJsonContains('cuisine_tags',$request->cuisine);
        $recipes = $q->orderByDesc('like_count')->paginate($request->per_page ?? 20);
        return response()->json(['success'=>true,'data'=>$recipes]);
    }

    public function show(string $uuid)
    {
        $recipe = Recipe::with(['user.profile','category','ingredients','steps','media'])->where('uuid',$uuid)->firstOrFail();
        $recipe->increment('view_count');
        $isLiked = auth()->check() ? $recipe->isLikedBy(auth()->user()) : false;
        $isSaved = auth()->check() ? $recipe->isSavedBy(auth()->user()) : false;
        return response()->json(['success'=>true,'data'=>array_merge($recipe->toArray(),['is_liked'=>$isLiked,'is_saved'=>$isSaved])]);
    }

    public function store(Request $request)
    {
        $request->validate(['title'=>'required|string|max:255','description'=>'nullable|string','category_id'=>'nullable|exists:recipe_categories,id','prep_time'=>'nullable|integer','cook_time'=>'nullable|integer','servings'=>'nullable|integer','difficulty'=>'nullable|in:easy,medium,hard,expert','ingredients'=>'required|array|min:1','steps'=>'required|array|min:1']);
        DB::beginTransaction();
        try {
            $recipe = Recipe::create(array_merge($request->only(['title','description','category_id','prep_time','cook_time','servings','difficulty','visibility']),['user_id'=>auth()->id(),'slug'=>Str::slug($request->title).'-'.Str::random(6),'status'=>'draft']));
            foreach ($request->ingredients as $i => $ing) {
                $recipe->ingredients()->create(array_merge($ing,['order_index'=>$i]));
            }
            foreach ($request->steps as $s => $step) {
                $recipe->steps()->create(array_merge($step,['step_number'=>$s+1]));
            }
            if ($request->hasFile('cover_image')) $recipe->update(['cover_image'=>$request->file('cover_image')->store("recipes/{$recipe->id}",'public')]);
            app(\App\Services\WalletService::class)->awardPoints(auth()->user(), 5, 'recipe', 'Uploaded recipe');
            auth()->user()->profile?->increment('recipe_count');
            DB::commit();
            return response()->json(['success'=>true,'data'=>$recipe->load('ingredients','steps')],201);
        } catch (\Throwable $e) { DB::rollBack(); return response()->json(['success'=>false,'message'=>$e->getMessage()],500); }
    }

    public function like(Recipe $recipe)
    {
        $user = auth()->user();
        $liked = RecipeLike::where('recipe_id',$recipe->id)->where('user_id',$user->id)->exists();
        if ($liked) { RecipeLike::where('recipe_id',$recipe->id)->where('user_id',$user->id)->delete(); $recipe->decrement('like_count'); return response()->json(['success'=>true,'liked'=>false]); }
        RecipeLike::create(['recipe_id'=>$recipe->id,'user_id'=>$user->id]);
        $recipe->increment('like_count');
        return response()->json(['success'=>true,'liked'=>true]);
    }

    public function save(Recipe $recipe)
    {
        $user = auth()->user();
        $saved = SavedRecipe::where('user_id',$user->id)->where('recipe_id',$recipe->id)->first();
        if ($saved) { $saved->delete(); $recipe->decrement('save_count'); return response()->json(['success'=>true,'saved'=>false]); }
        SavedRecipe::create(['user_id'=>$user->id,'recipe_id'=>$recipe->id]);
        $recipe->increment('save_count');
        return response()->json(['success'=>true,'saved'=>true]);
    }

    public function categories()
    {
        return response()->json(['success'=>true,'data'=>\App\Models\RecipeCategory::where('is_active',true)->orderBy('sort_order')->get()]);
    }
}
