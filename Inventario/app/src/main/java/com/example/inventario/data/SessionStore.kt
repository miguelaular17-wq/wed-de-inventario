package com.example.inventario.data

import android.content.Context
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map

private val Context.sessionDataStore by preferencesDataStore(name = "session")

interface TokenProvider {
    suspend fun token(): String?
}

class SessionStore(private val context: Context) : TokenProvider {
    private val tokenKey = stringPreferencesKey("bearer_token")

    val tokenFlow: Flow<String?> = context.sessionDataStore.data.map { it[tokenKey] }

    override suspend fun token(): String? = tokenFlow.first()

    suspend fun saveToken(token: String) {
        context.sessionDataStore.edit { it[tokenKey] = token }
    }

    suspend fun clear() {
        context.sessionDataStore.edit { it.remove(tokenKey) }
    }
}
