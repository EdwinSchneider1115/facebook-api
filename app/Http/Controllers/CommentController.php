<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\Request;
use App\Mail\NewCommentMail;
use Illuminate\Support\Facades\Mail;

class CommentController extends Controller
{
    /**
     * Listar comentarios de una publicación
     *
     * Devuelve los comentarios de la publicación indicada.
     *
     * @group Comentarios
     * @urlParam postId integer required Identificador de la publicación. Example: 1
     * @response 200 [{"id":1,"post_id":1,"author":"Ana Prueba","content":"Qué buena publicación"}]
     * @response 404 {"message":"No query results for model [App\\Models\\Post] 999"}
     */
    public function index($postId)
    {
        $post = Post::findOrFail($postId);

        return response()->json($post->comments()->with('user')->latest()->get(), 200);
    }

    /**
     * Agregar comentario a una publicación
     *
     * Crea un comentario asociado al usuario autenticado y a la publicación indicada.
     *
     * @group Comentarios
     * @authenticated
     * @urlParam postId integer required Identificador de la publicación. Example: 1
     * @bodyParam content string required Texto del comentario. Example: Qué buena publicación
     * @response 201 {"id":1,"post_id":1,"author":"Ana Prueba","content":"Qué buena publicación"}
     * @response 401 {"message":"Unauthenticated."}
     * @response 422 {"message":"The content field is required."}
     */
    public function store(Request $request, $postId)
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
        ]);

        $post = Post::findOrFail($postId);
        $user = $request->user();

        $comment = $post->comments()->create([
            'user_id' => $user->id,
            'author' => $user->name,
            'content' => $validated['content'],
        ]);

        if ($post->user && $post->user->email) {
            Mail::to($post->user->email)->send(new NewCommentMail($comment));
        }

        return response()->json($comment->load('user'), 201);
    }
}