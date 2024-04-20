<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\User;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    //
    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['permission:users.index|users.create|users.edit|users.delete']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = User::latest()->when(request()->q, function($users) {
            $users = $users->where('name', 'like', '%'. request()->q . '%');
        })->get();

        return view('admin.user.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $action = "store";

        $roles = Role::latest()->get();
        return view('admin.user.form', compact('roles','action'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'name'      => 'required',
            'email'     => 'required|email|unique:users',
            'password'  => 'required|confirmed'
        ]);

        $user = User::create([
            'name'      => $request->input('name'),
            'email'     => $request->input('email'),
            'password'  => bcrypt($request->input('password'))
        ]);

        //assign role
        $user->assignRole($request->input('role'));

        if($user){
            //redirect dengan pesan sukses
            return redirect()->route('admin.user.index')->with(['success' => 'Data Berhasil Disimpan!']);
        }else{
            //redirect dengan pesan error
            return redirect()->route('admin.user.index')->with(['error' => 'Data Gagal Disimpan!']);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $action = "update";
        $data = User::find($id);
        $roles = Role::latest()->get();
        return view('admin.user.form', compact('data', 'roles', 'action'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        $this->validate($request, [
            'name'      => 'required',
            'email'     => 'required|email|unique:users,email,'.$user->id
        ]);

        $user = User::findOrFail($user->id);

        if($request->input('password') == "") {
            $user->update([
                'name'      => $request->input('name'),
                'email'     => $request->input('email')
            ]);
        } else {
            $user->update([
                'name'      => $request->input('name'),
                'email'     => $request->input('email'),
                'password'  => bcrypt($request->input('password'))
            ]);
        }

        //assign role
        $user->syncRoles($request->input('role'));

        if($user){
            //redirect dengan pesan sukses
            return redirect()->route('admin.user.index')->with(['success' => 'Data Berhasil Diupdate!']);
        }else{
            //redirect dengan pesan error
            return redirect()->route('admin.user.index')->with(['error' => 'Data Gagal Diupdate!']);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();


        if($user){
            return response()->json([
                'status' => 'success'
            ]);
        }else{
            return response()->json([
                'status' => 'error'
            ]);
        }
    }
    public function destroyFaceCalibration($id)
    {
        $face = UserFace::findOrFail($id);
        if($face->image){
            $image = Storage::disk('local')->delete('public/users/'.$face->image);
        }
        $face->delete();


        if($face){
            return response()->json([
                'status' => 'success'
            ]);
        }else{
            return response()->json([
                'status' => 'error'
            ]);
        }
    }
    public function myFaceCalibration()
    {
        $user = Auth::user();
     
        return view('admin.user.my-face-calibration', compact('user'));

    }
    public function myStoreFaceCalibration(Request $request)
    {
        $user = Auth::user();
        $count = $user->faces->count() + 1;
        if($request->image) {
            $image = $request->image;  // your base64 encoded
            // dd($image);
            $image = str_replace('data:image/png;base64,', '', $image);
            $image = str_replace(' ', '+', $image);
            $imageName = $user->id.''.$user->name.''.$count.'.png';
            $data =  base64_decode($image);
       
            $file = Storage::disk('local')->put('public/users/'.$imageName, $data);
        }else if($request->file('image_document')){
      
            //upload file
            $file = $request->file('image_document');
            $imageName = $user->id.''.$user->name.''.$count.'.'.$file->extension();
       
            $path = 'users/' .$imageName;
            $file->storeAs('public/', $path);
        }

        
      
        $response = Http::attach(
            'image', Storage::get('public/users/'.$imageName), $imageName
        )->post('http://127.0.0.1:5000/recognize');
        $response = json_decode($response,true);
        
        $arr = '['.implode(', ', $response[0]['encoding']).']';
        $temp_product['image'] = $imageName;
        $temp_product['name'] = $user->name;
        $temp_product['encoding'] = $arr;
            // dd($temp_product);
        $user->faces()->create($temp_product);
        
        return redirect()->route('my.user.calibration')->with(['success' => 'Kalibrasi berhasil!']);
    }
}