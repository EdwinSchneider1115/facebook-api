<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * @group Publicaciones
 *
 * Endpoints para consultar, crear y eliminar publicaciones, y darles like.
 */
class PostController extends Controller
{
    /**
     * Listar publicaciones
     *
     * Devuelve las publicaciones ordenadas de más reciente a más antigua,
     * con sus imágenes, comentarios y usuario. La respuesta está paginada.
     *
     * @queryParam search string Busca el texto en el título o el contenido. Example: viaje
     * @queryParam page integer Número de página que se solicita. Example: 1
     * @response 200 {"data":[{"id":1,"title":"Viaje","content":"Fotos de mi viaje","likes_count":0}],"current_page":1,"per_page":10,"total":1}
     */
    public function index(Request $request)
    {
        $posts = Post::with(['images', 'comments', 'user'])
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10);

        return response()->json($posts, 200);
    }

    /**
     * Mostrar una publicación
     *
     * Devuelve una publicación específica junto con sus imágenes,
     * comentarios y usuario.
     *
     * @urlParam id integer required Identificador de la publicación. Example: 1
     * @response 200 {"id":1,"title":"Mi publicación","content":"Contenido de prueba","likes_count":0}
     * @response 404 {"message":"No query results for model [App\\Models\\Post] 999"}
     */
    public function show($id)
    {
        $post = Post::with(['images', 'comments', 'user'])->findOrFail($id);

        return response()->json($post, 200);
    }

    /**
     * Crear una publicación
     *
     * Crea una publicación para el usuario autenticado. Se pueden enviar
     * imágenes como archivos opcionales.
     *
     * @authenticated
     * @bodyParam title string required Título de la publicación. Example: Mi primera publicación
     * @bodyParam content string required Texto de la publicación. Example: Hola, comparto una foto
     * @bodyParam images[] file Archivo de imagen opcional. Puede enviarse más de una imagen.
     * @response 201 {"id":1,"title":"Mi primera publicación","content":"Hola, comparto una foto","user_id":1,"likes_count":0}
     * @response 401 {"message":"Unauthenticated."}
     * @response 422 {"message":"The title field is required.","errors":{"title":["The title field is required."]}}
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);

        $post = Post::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'likes_count' => 0,
            'user_id' => $request->user()->id,
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('posts', 'public');

                PostImage::create([
                    'post_id' => $post->id,
                    'image_path' => $path,
                ]);
            }
        }

        return response()->json($post->load(['images', 'user']), 201);
    }

    /**
     * Dar like a una publicación
     *
     * Registra el like del usuario autenticado. Si ese usuario ya dio like,
     * la asociación no se duplica.
     *
     * @authenticated
     * @urlParam id integer required Identificador de la publicación. Example: 1
     * @response 200 {"message":"Me gusta añadido correctamente.","likes_count":1}
     * @response 401 {"message":"Unauthenticated."}
     * @response 404 {"message":"No query results for model [App\\Models\\Post] 999"}
     */
    public function like($id)
    {
        $post = Post::findOrFail($id);

        $post->usersWhoLiked()->syncWithoutDetaching([Auth::id()]);

        return response()->json([
            'message' => 'Me gusta añadido correctamente.',
            'likes_count' => $post->usersWhoLiked()->count(),
        ], 200);
    }

    /**
     * Eliminar una publicación
     *
     * Elimina la publicación y sus archivos de imagen. Solo el usuario que
     * creó la publicación puede eliminarla.
     *
     * @authenticated
     * @urlParam id integer required Identificador de la publicación. Example: 1
     * @response 204
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"No tienes permiso para eliminar esta publicación."}
     * @response 404 {"message":"No query results for model [App\\Models\\Post] 999"}
     */
    public function destroy($id)
    {
        $post = Post::with('images')->findOrFail($id);

        if ($post->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'No tienes permiso para eliminar esta publicación.',
            ], 403);
        }

        foreach ($post->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $post->delete();

        return response()->json(null, 204);
    }
}
