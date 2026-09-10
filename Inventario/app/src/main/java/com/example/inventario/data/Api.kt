package com.example.inventario.data

import kotlinx.coroutines.runBlocking
import kotlinx.serialization.json.Json
import okhttp3.Interceptor
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.Response
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

interface InventarioApi {
    @POST("api/v1/auth/login")
    suspend fun login(@Body request: LoginRequest): LoginResponse

    @GET("api/v1/auth/me")
    suspend fun me(): MeResponse

    @POST("api/v1/auth/logout")
    suspend fun logout(): Response<Unit>

    @GET("api/v1/sedes")
    suspend fun sedes(): SitesResponse

    @GET("api/v1/inventario")
    suspend fun inventory(
        @Query("page") page: Int,
        @Query("q") query: String? = null,
        @Query("categoria") category: String? = null,
        @Query("sede") site: String,
    ): InventoryPageDto

    @GET("api/v1/requisiciones")
    suspend fun requisitions(
        @Query("estado") status: String? = null,
        @Query("sede") site: String,
    ): RequisitionsResponse

    @GET("api/v1/inventario/productos/{codigo}/metricas")
    suspend fun metrics(
        @Path("codigo") code: String,
        @Query("sede_origen") origin: String,
        @Query("cantidad") quantity: Int,
        @Query("sede") site: String,
    ): MetricsResponse

    @POST("api/v1/requisiciones")
    suspend fun createRequisition(@Body request: CreateRequisitionRequest): RequisitionResponse

    @DELETE("api/v1/requisiciones/{id}")
    suspend fun cancelRequisition(
        @Path("id") id: Long,
        @Query("sede") site: String,
    ): Response<Unit>
}

class BearerTokenInterceptor(private val tokenProvider: TokenProvider) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): okhttp3.Response {
        val token = runBlocking { tokenProvider.token() }
        val request = chain.request().newBuilder().apply {
            if (!token.isNullOrBlank()) header("Authorization", "Bearer $token")
            header("Accept", "application/json")
        }.build()
        return chain.proceed(request)
    }
}

object ApiFactory {
    private val json = Json {
        ignoreUnknownKeys = true
        isLenient = true
        explicitNulls = false
        coerceInputValues = true
    }

    fun create(baseUrl: String, tokenProvider: TokenProvider): InventarioApi {
        val client = OkHttpClient.Builder()
            .addInterceptor(BearerTokenInterceptor(tokenProvider))
            .addInterceptor(
                HttpLoggingInterceptor().apply {
                    level = HttpLoggingInterceptor.Level.BASIC
                },
            )
            .build()

        return Retrofit.Builder()
            .baseUrl(normalizeBaseUrl(baseUrl))
            .client(client)
            .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
            .build()
            .create(InventarioApi::class.java)
    }
}

fun normalizeBaseUrl(value: String): String {
    val trimmed = value.trim()
    require(trimmed.startsWith("http://") || trimmed.startsWith("https://")) {
        "INVENTARIO_BASE_URL debe comenzar con http:// o https://"
    }
    return if (trimmed.endsWith('/')) trimmed else "$trimmed/"
}
