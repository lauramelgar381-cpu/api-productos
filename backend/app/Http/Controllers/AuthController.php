<?php

namespace App\Http\Controllers;

use App\Mail\OtpCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/register',
        summary: 'Registrar un nuevo usuario',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Laura'),
                    new OA\Property(property: 'email', type: 'string', example: 'laura@test.com'),
                    new OA\Property(property: 'password', type: 'string', example: '123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Usuario registrado, devuelve el token'),
            new OA\Response(response: 422, description: 'Error de validación'),
        ]
    )]
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole('usuario');

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $this->userConPermisos($user),
            'token' => $token,
        ], 201);
    }

    #[OA\Post(
        path: '/api/login',
        summary: 'Iniciar sesión (si tiene 2FA devuelve un challenge en vez del token)',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'laura@test.com'),
                    new OA\Property(property: 'password', type: 'string', example: '123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login exitoso, devuelve el token o el challenge 2FA'),
            new OA\Response(response: 422, description: 'Credenciales inválidas'),
        ]
    )]
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        return $this->respuestaLogin($user);
    }

    #[OA\Post(
        path: '/api/auth/google',
        summary: 'Iniciar sesión con Google (credential de Google Identity Services)',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['credential'],
                properties: [
                    new OA\Property(property: 'credential', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login exitoso'),
            new OA\Response(response: 422, description: 'Token de Google inválido'),
        ]
    )]
    public function google(Request $request)
    {
        $request->validate(['credential' => 'required|string']);

        // Google verifica la firma y la vigencia del token
        $respuesta = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $request->credential,
        ]);

        if (
            $respuesta->failed()
            || $respuesta->json('aud') !== config('services.google.client_id')
            || $respuesta->json('email_verified') !== 'true'
        ) {
            throw ValidationException::withMessages([
                'credential' => ['No se pudo validar la cuenta de Google.'],
            ]);
        }

        $email = $respuesta->json('email');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $respuesta->json('name') ?? $email,
                'password' => Hash::make(Str::random(32)), // no se usará: entra con Google
            ],
        );

        if ($user->wasRecentlyCreated) {
            $user->assignRole('usuario');
        }

        return $this->respuestaLogin($user);
    }

    #[OA\Post(
        path: '/api/logout',
        summary: 'Cerrar sesión (revoca el token actual)',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sesión cerrada correctamente'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    #[OA\Get(
        path: '/api/me',
        summary: 'Usuario autenticado con permisos',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Usuario y permisos')]
    )]
    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->userConPermisos($request->user()),
        ]);
    }

    // ---------------------------------------------------------------
    // Login con código por correo
    // ---------------------------------------------------------------

    #[OA\Post(
        path: '/api/auth/code/request',
        summary: 'Enviar un código de 6 dígitos al correo',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [new OA\Property(property: 'email', type: 'string', example: 'laura@test.com')]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Respuesta idéntica exista o no el correo')]
    )]
    public function requestCode(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            DB::table('login_codes')->where('email', $user->email)->delete();

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::table('login_codes')->insert([
                'email' => $user->email,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                Mail::to($user->email)->send(new OtpCodeMail($code));
            } catch (\Exception $e) {
                Log::error('Error al enviar el correo de código: '.$e->getMessage());
            }
        }

        // Misma respuesta exista o no el correo, para no revelar quién está registrado
        return response()->json(['message' => 'Si el correo existe, enviamos un código.']);
    }

    #[OA\Post(
        path: '/api/auth/code/verify',
        summary: 'Verificar el código recibido por correo',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'code'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'laura@test.com'),
                    new OA\Property(property: 'code', type: 'string', example: '123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Código correcto, devuelve token o challenge 2FA'),
            new OA\Response(response: 422, description: 'Código inválido o expirado'),
        ]
    )]
    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|digits:6',
        ]);

        $invalido = ValidationException::withMessages([
            'code' => ['El código no es válido o expiró.'],
        ]);

        $registro = DB::table('login_codes')->where('email', $request->email)->first();

        if (! $registro || now()->gt($registro->expires_at) || $registro->attempts >= 5) {
            throw $invalido;
        }

        if (! Hash::check($request->code, $registro->code_hash)) {
            DB::table('login_codes')->where('id', $registro->id)->increment('attempts');
            throw $invalido;
        }

        DB::table('login_codes')->where('id', $registro->id)->delete();

        $user = User::where('email', $request->email)->firstOrFail();

        return $this->respuestaLogin($user);
    }

    // ---------------------------------------------------------------
    // 2FA con QR (Google Authenticator)
    // ---------------------------------------------------------------

    #[OA\Post(
        path: '/api/2fa/setup',
        summary: 'Generar el secreto y la URL otpauth para el QR',
        tags: ['2FA'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'otpauth_url y secret')]
    )]
    public function twoFactorSetup(Request $request)
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            throw ValidationException::withMessages([
                'code' => ['El 2FA ya está activado.'],
            ]);
        }

        $g = new Google2FA();
        $secret = $g->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_last_timestamp' => null,
        ])->save();

        return response()->json([
            'otpauth_url' => $g->getQRCodeUrl(config('app.name'), $user->email, $secret),
            'secret' => $secret,
        ]);
    }

    #[OA\Post(
        path: '/api/2fa/enable',
        summary: 'Confirmar el 2FA con un código de 6 dígitos',
        tags: ['2FA'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [new OA\Property(property: 'code', type: 'string', example: '123456')]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '2FA activado'),
            new OA\Response(response: 422, description: 'Código inválido'),
        ]
    )]
    public function twoFactorEnable(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);
        $user = $request->user();

        if (! $user->two_factor_secret || $user->two_factor_confirmed_at) {
            throw ValidationException::withMessages([
                'code' => ['Inicia la configuración del 2FA primero.'],
            ]);
        }

        $ts = (new Google2FA())->verifyKeyNewer(
            $user->two_factor_secret,
            $request->code,
            (int) ($user->two_factor_last_timestamp ?? 0)
        );

        if ($ts === false) {
            throw ValidationException::withMessages([
                'code' => ['El código no es válido.'],
            ]);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_last_timestamp' => $ts,
        ])->save();

        return response()->json(['user' => $this->userConPermisos($user->fresh())]);
    }

    #[OA\Post(
        path: '/api/auth/2fa/challenge',
        summary: 'Completar el login con el código de Google Authenticator',
        tags: ['2FA'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['challenge', 'code'],
                properties: [
                    new OA\Property(property: 'challenge', type: 'string'),
                    new OA\Property(property: 'code', type: 'string', example: '123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Devuelve el token'),
            new OA\Response(response: 422, description: 'Código inválido o sesión expirada'),
        ]
    )]
    public function twoFactorChallenge(Request $request)
    {
        $request->validate([
            'challenge' => 'required|string',
            'code' => 'required|digits:6',
        ]);

        $clave = "2fa:{$request->challenge}";
        $claveIntentos = "2fa-intentos:{$request->challenge}";

        $invalido = ValidationException::withMessages([
            'code' => ['El código no es válido o la sesión expiró.'],
        ]);

        $userId = Cache::get($clave);
        $intentos = (int) Cache::get($claveIntentos, 0);

        if (! $userId || $intentos >= 5) {
            throw $invalido;
        }

        $user = User::find($userId);

        $ts = $user
            ? (new Google2FA())->verifyKeyNewer(
                $user->two_factor_secret,
                $request->code,
                (int) ($user->two_factor_last_timestamp ?? 0)
            )
            : false;

        if ($ts === false) {
            Cache::put($claveIntentos, $intentos + 1, now()->addMinutes(5));
            throw $invalido;
        }

        Cache::forget($clave);
        Cache::forget($claveIntentos);

        $user->forceFill(['two_factor_last_timestamp' => $ts])->save();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $this->userConPermisos($user->fresh()),
            'token' => $token,
        ]);
    }

    #[OA\Post(
        path: '/api/2fa/disable',
        summary: 'Desactivar el 2FA (pide la contraseña)',
        tags: ['2FA'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password'],
                properties: [new OA\Property(property: 'password', type: 'string')]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '2FA desactivado'),
            new OA\Response(response: 422, description: 'Contraseña incorrecta'),
        ]
    )]
    public function twoFactorDisable(Request $request)
    {
        $request->validate(['password' => 'required|string']);
        $user = $request->user();

        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['La contraseña no es correcta.'],
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestamp' => null,
        ])->save();

        return response()->json(['user' => $this->userConPermisos($user->fresh())]);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function userConPermisos(User $user): array
    {
        return array_merge($user->toArray(), [
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
        ]);
    }

    private function respuestaLogin(User $user)
    {
        if ($user->two_factor_confirmed_at) {
            $challenge = Str::random(40);
            Cache::put("2fa:{$challenge}", $user->id, now()->addMinutes(5));

            return response()->json(['two_factor' => true, 'challenge' => $challenge]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $this->userConPermisos($user),
            'token' => $token,
        ]);
    }
}
