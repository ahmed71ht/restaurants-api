<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RestaurantComment;
use Illuminate\Http\Request;

class RestaurantCommentController extends Controller
{
    /**
     * Store comment
     */
    public function store(Request $request)
    {
        $request->validate([
            'comment' => 'required|min:3',
            'restaurant_id' => 'required|exists:restaurants,id',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $imageName = null;

        if ($request->hasFile('image')) {
            $imageName = $request->file('image')->store('comments', 'public');
        }

        $comment = \App\Models\RestaurantComment::create([
            'user_id' => $request->user()->id,
            'restaurant_id' => $request->restaurant_id,
            'comment' => $request->comment,
            'image' => $imageName,
        ]);

        return response()->json([
            'message' => 'تم إضافة التعليق بنجاح',
            'comment' => $comment
        ]);
    }
    /**
     * Update comment
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'comment' => 'required|min:3',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $comment = \App\Models\RestaurantComment::findOrFail($id);

        if ($comment->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'غير مسموح'
            ], 403);
        }

        // إذا في صورة جديدة
        if ($request->hasFile('image')) {

            // حذف القديمة
            if ($comment->image) {
                \Storage::disk('public')->delete($comment->image);
            }

            // رفع الجديدة
            $comment->image = $request->file('image')->store('comments', 'public');
        }

        $comment->comment = $request->comment;
        $comment->save();

        return response()->json([
            'message' => 'تم تحديث التعليق',
            'comment' => $comment
        ]);
    }

    /**
     * Delete comment
     */
    public function destroy($id, Request $request)
    {
        $comment = RestaurantComment::findOrFail($id);

        $restaurantOwnerId = $comment->restaurant->owner_id;
        $user = $request->user();

        if (
            $comment->user_id !== $user->id &&
            $user->role !== 'admin' &&
            $user->id !== $restaurantOwnerId
        ) {
            return response()->json([
                'message' => 'غير مسموح بحذف التعليق'
            ], 403);
        }

        if ($comment->image && file_exists(public_path('comments/' . $comment->image))) {
            unlink(public_path('comments/' . $comment->image));
        }

        $comment->delete();

        return response()->json([
            'message' => 'تم حذف التعليق'
        ]);
    }
}