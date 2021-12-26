<?php

namespace Amcoders\Plugin\product\http\controllers;

use App\Category;
use App\Http\Controllers\Controller;
use App\Imports\ProductsImport;
use App\Meta;
use App\PostCategory;
use App\Productmeta;
use App\Terms;
use App\User;
use Auth;
use Excel;
use Exception;
use File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!Auth()->user()->can('product.list')) {
            return abort(401);
        }
        if (!empty($request->src)) {
            $src = $request->src;
            $posts = Terms::with('preview', 'price', 'user')->withCount('order')->where('type', 6)->where($request->type, $request->src)->latest()->paginate(30);
            // return response()->json($posts);
            return view('plugin::admin.products', compact('posts', 'src'));
        }
        $posts = Terms::with('preview', 'price', 'user')->withCount('order')->where('type', 6)->latest()->paginate(30);
        // return response()->json($posts);

        return view('plugin::admin.products', compact('posts'));
    }

    public function checkProductAvailabilty(Request $request)
    {
        if (!empty($request->store)) {
            if ($request->check_availability) {
                try {
                    $data = Excel::toCollection(new ProductsImport, $request->file('file'));
    
                    if ($data->count() > 0) {
                        $products_id = array();
                        foreach ($data->toArray() as $key => $value) {
    
                            foreach ($value as $row) {
    
                                if ($row[0] != "ID" && $row[1] != "ProductTitle" && $row[1] != null &&
                                    $row[2] != "Price" && $row[2] != null && $row[2] != "Price" && $row[3] != null &&
                                    $row[4] != "Description" && $row[4] != null && $row[5] != "Image" && $row[5] != null && $row[6] != "Page URL") {
    
                                    $is_available = Terms::where("title", $row[1])->where("auth_id", $request->store)
                                    ->join("product_meta", "product_meta.term_id", "terms.id")
                                    ->where("product_meta.price", $row[2])
                                    ->first();
                                    if ($is_available) {
                                        $products_id[] = $is_available->id;
                                    }
    
                                }
    
                            }
    
                        }
    
                        $posts = Terms::whereIn("id", $products_id)->with('preview', 'price', 'user')->withCount('order')->where('type', 6)->latest()->paginate(30);
                        $users = User::select('id', 'name')->where('role_id', 3)->where('status', '!=', 'pending')->with("resturentlocationwithcity")->get();
                        return view('plugin::admin.product_availability', compact('posts'))->with("users", $users)->with("table_type", "availability");
    
                    } else {
                        $posts = Terms::where("id", 0)->with('preview', 'price', 'user')->withCount('order')->where('type', 6)->latest()->paginate(30);
                        $users = User::select('id', 'name')->where('role_id', 3)->where('status', '!=', 'pending')->with("resturentlocationwithcity")->get();
                        return view('plugin::admin.product_availability', compact('posts'))->with("users", $users)->with("table_type", "availability");
                    }
                } catch (Exception $e) {
                    return back()->withError($e->getMessage())->withInput();
                }
            }

            if ($request->check_unavailability) {
                try {
                    $data = Excel::toCollection(new ProductsImport, $request->file('file'));
    
                    if ($data->count() > 0) {
                        $posts = array();
                        foreach ($data->toArray() as $key => $value) {
    
                            foreach ($value as $row) {
    
                                if ($row[0] != "ID" && $row[1] != "ProductTitle" && $row[1] != null &&
                                    $row[2] != "Price" && $row[2] != null && $row[2] != "Price" && $row[3] != null &&
                                    $row[4] != "Description" && $row[4] != null && $row[5] != "Image" && $row[5] != null && $row[6] != "Page URL") {
    
                                    $is_available = Terms::where("title", $row[1])->where("auth_id", $request->store)
                                    ->join("product_meta", "product_meta.term_id", "terms.id")
                                    ->where("product_meta.price", $row[2])
                                    ->first();
                                    if (!$is_available) {
                                        $posts[] = [
                                            "store_id" => $request->store,
                                            "id" => $row[0],
                                            "title" => $row[1],
                                            "price" => $row[2],
                                            "description" => str_replace("_x000D_", "</br>", $row[4]),
                                            "image" => $row[5]
                                        ];
                                    }
    
                                }
    
                            }
    
                        }
                        
                        $users = User::select('id', 'name')->where('role_id', 3)->where('status', '!=', 'pending')->with("resturentlocationwithcity")->get();
                        return view('plugin::admin.product_availability', compact('posts'))->with("users", $users)->with("table_type", "unavailability");
    
                    } else {
                        $posts = Terms::where("id", 0)->with('preview', 'price', 'user')->withCount('order')->where('type', 6)->latest()->paginate(30);
                        $users = User::select('id', 'name')->where('role_id', 3)->where('status', '!=', 'pending')->with("resturentlocationwithcity")->get();
                        return view('plugin::admin.product_availability', compact('posts'))->with("users", $users)->with("table_type", "availability");
                    }
                } catch (Exception $e) {
                    return back()->withError($e->getMessage())->withInput();
                }
            }
            

        } else {

            if($request->upload_products){
               
                try{
                   
                    
                    $count = 0;
                    foreach ($request->product_data as $key => $value) {
                        $product_details = explode("(--$$--)", $value);

                        $is_product_available = Terms::where("title", $product_details[1])->where("auth_id", $product_details[0])
                        ->join("product_meta", "product_meta.term_id", "terms.id")
                        ->where("product_meta.price", $product_details[3])
                        ->first();
                        if(!$is_product_available){
                            $slug = Str::slug($product_details[1]);
                            if ($slug == '') {
                                $slug = str_replace(' ', '-', $product_details[1]);
                            }
                            $post = new Terms;
                            $post->title = $product_details[1];
                            $post->slug = $slug;
                            $post->type = 6;
                            $post->auth_id = $product_details[0];
                            $post->status = 1;
                            $post->save();
            
                            $post_meta = new Meta;
                            $post_meta->term_id = $post->id;
                            $post_meta->type = 'excerpt';
                            $post_meta->content = $product_details[2];
                            $post_meta->save();
            
                            $post_meta = new Meta;
                            $post_meta->term_id = $post->id;
                            $post_meta->type = 'preview';
                            $post_meta->content = $product_details[4];
                            $post_meta->save();
            
                            $product = new Productmeta;
                            $product->term_id = $post->id;
                            $product->price = $product_details[3];
                            $product->save();
            
                            $categories = Category::where('user_id', $product_details[0])->where("name", $row[6])->first();
            
                            if (!$categories) {
                                $category_slug=Str::slug($row[6]);
                                if ($category_slug=='') {
                                    $category_slug=str_replace(' ', '-', $row[6]);
                                }
                                $category = new Category;
                                $category->name = $row[6];
                                $category->avatar = null;
                                $category->slug = $category_slug;
                                $category->p_id = null;
                                $category->type = 1;
                                $category->user_id = $product_details[0];
                                $category->save();
            
                                $cat = new PostCategory;
                                $cat->term_id = $post->id;
                                $cat->category_id = $category->id;
                                $cat->save();
                            } else {
                                $cat = new PostCategory;
                                $cat->term_id = $post->id;
                                $cat->category_id = $categories->id;
                                $cat->save();
                            }
                            $count++;
                        }
                        }
                       
                    return redirect()->route("admin.check.product.availability")->withSuccess($count. " Products are uploaded Successfully")->withInput();
                    
                }catch (Exception $e) {
                    return redirect()->route("admin.check.product.availability")->withError($e->getMessage())->withInput();
                }
               
                
            }else{
                $posts = Terms::where("id", 0)->with('preview', 'price', 'user')->withCount('order')->where('type', 6)->latest()->paginate(30);

                $users = User::select('id', 'name')->where('role_id', 3)->where('status', '!=', 'pending')->with("resturentlocationwithcity")->get();
    
                return view('plugin::admin.product_availability', compact('posts'))->with("users", $users)->with("table_type", "availability");
            }
            
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        if (!Auth()->user()->can('product.delete')) {
            return abort(401);
        }
        if ($request->status == 'delete') {
            if ($request->ids) {
                foreach ($request->ids as $id) {
                    Terms::destroy($id);
                }
            }
        }

        return response()->json('Product Removed');

    }

    public function importExcelView()
    {

        $users = User::select('id', 'name')->where('role_id', 3)->where('status', '!=', 'pending')->with("resturentlocationwithcity")->get();
       
      // return response()->json($users);
        return view('plugin::admin.import_view')->with("users", $users);
    }

    public function importExcelData(Request $request)
    {

        $this->validate($request, [
            'file' => 'required|mimes:xls,xlsx',
            'store' => 'required',
        ]);

        try {

            $data = Excel::toCollection(new ProductsImport, $request->file('file'));

            //  return response()->json($row);
            //    $data = Excel::import($path)->get();

            if ($data->count() > 0) {
                foreach ($data->toArray() as $key => $value) {

                    foreach ($value as $row) {

                        if ($row[0] != "ID" && $row[1] != "ProductTitle" && $row[1] != null &&
                            $row[2] != "Price" && $row[2] != null && $row[2] != "Price" && $row[3] != null &&
                            $row[4] != "Description" && $row[4] != null && $row[5] != "Image" && $row[5] != null && $row[6] != "Page URL") {
                                $category_products = Category::where("user_id", $request->store)->where("type", 1)->where("name", $row[6])->first();
        
                                if($category_products){
                                    $post_category_ids = PostCategory::where("category_id",$category_products->id)->pluck("term_id");

                                    Terms::whereIn("id",$post_category_ids)->delete();
                                    PostCategory::where("category_id",$category_products->id)->delete();
                                    Category::where("user_id", $request->store)->where("type", 1)->where("name", $row[6])->delete();
                                }
                         break;
                        }

                    }


                    foreach ($value as $row) {
                        if ($row[0] != "ID" && $row[1] != "ProductTitle" && $row[1] != null &&
                            $row[2] != "Price" && $row[2] != null && $row[2] != "Price" && $row[3] != null &&
                            $row[4] != "Description" && $row[4] != null && $row[5] != "Image" && $row[5] != null && $row[6] != "Page URL") {

                            
                                
                           
                            $is_term_available = Terms::where("title", $row[1])->where("auth_id", $request->store)
                            ->join("post_category", "post_category.term_id", "terms.id")    
                            ->join("categories", "categories.id", "post_category.category_id")
                            ->where("categories.name", $row[6])
                            ->join("product_meta", "product_meta.term_id", "terms.id")
                           
                                ->where("product_meta.price", $row[2])
                                ->first();

                            if (!$is_term_available) {

                                $slug = Str::slug($row[1]);
                                if ($slug == '') {
                                    $slug = str_replace(' ', '-', $row[1]);
                                }

                                $post = new Terms;
                                $post->title = $row[1];
                                $post->slug = $slug;
                                $post->type = 6;
                                $post->auth_id = $request->store;
                                $post->status = 1;
                                $post->save();

                                $post_meta = new Meta;
                                $post_meta->term_id = $post->id;
                                $post_meta->type = 'excerpt';
                                $post_meta->content = str_replace("_x000D_", "</br>", $row[4]);
                                $post_meta->save();

                                $post_meta = new Meta;
                                $post_meta->term_id = $post->id;
                                $post_meta->type = 'preview';
                                $post_meta->content = $row[5];
                                $post_meta->save();

                                $product = new Productmeta;
                                $product->term_id = $post->id;
                                $product->price = $row[2];
                                $product->save();

                                $categories = Category::where('user_id', $request->store)->where("name", $row[6])->first();

                                if (!$categories) {
                                    $category_slug=Str::slug($row[6]);
                                    if ($category_slug=='') {
                                        $category_slug=str_replace(' ', '-', $row[6]);
                                    }
                                    $category = new Category;
                                    $category->name = $row[6];
                                    $category->avatar = null;
                                    $category->slug = $category_slug;
                                    $category->p_id = null;
                                    $category->type = 1;
                                    $category->user_id = $request->store;
                                    $category->save();

                                    $cat = new PostCategory;
                                    $cat->term_id = $post->id;
                                    $cat->category_id = $category->id;
                                    $cat->save();
                                } else {
                                    $cat = new PostCategory;
                                    $cat->term_id = $post->id;
                                    $cat->category_id = $categories->id;
                                    $cat->save();
                                }
                            }

                        }

                        // if ($request->category) {

                        //  foreach ($request->category as $cat_row) {

                        //         $cat= new PostCategory;
                        //         $cat->term_id=$post->id;
                        //         $cat->category_id=$cat_row;
                        //         $cat->save();

                        //  }
                        // }

                    }
                }

                return response()->json(["Data Insert Successfully"]);

                // if(!empty($insert_data))
                // {
                //  DB::table('tbl_customer')->insert($insert_data);
                // }
            } else {
                return response()->json(["Failed"]);
            }
        } catch (Exception $e) {
            return response()->json([$e->getMessage()]);
        }

    }
}
