<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommentReply;
use Illuminate\Http\Request;

class CommentReplyController extends Controller
{
    /**
     * Store reply
     */
    public function store(Request $request, \App\Models\RestaurantComment $comment)
    {
        $request->validate([
            'reply' => 'required|min:3',
        ]);

        $reply = CommentReply::create([
            'user_id'    => $request->user()->id,
            'comment_id' => $comment->id,
            'reply'      => $request->reply,
        ]);

        return response()->json([
            'message' => 'تم إضافة الرد بنجاح',
            'reply' => $reply
        ]);
    }

    /**
     * Update reply
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'reply' => 'required|min:3',
        ]);

        $reply = CommentReply::findOrFail($id);

        // فقط صاحب الرد
        if ($reply->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'غير مسموح'
            ], 403);
        }

        $reply->update([
            'reply' => $request->reply
        ]);

        return response()->json([
            'message' => 'تم تعديل الرد بنجاح',
            'reply' => $reply
        ]);
    }

    /**
     * Delete reply
     */
    public function destroy(Request $request, $id)
    {
        $reply = CommentReply::findOrFail($id);

        $user = $request->user();

        $restaurantOwnerId = optional($reply->comment?->restaurant)->owner_id;

        if (
            $reply->user_id !== $user->id &&
            $user->role !== 'admin' &&
            $user->id !== $restaurantOwnerId
        ) {
            return response()->json([
                'message' => 'غير مسموح بحذف الرد'
            ], 403);
        }

        $reply->delete();

        return response()->json([
            'message' => 'تم حذف الرد'
        ]);
    }
}