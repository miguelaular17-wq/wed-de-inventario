package com.example.inventario

import android.content.Context
import com.example.inventario.data.ApiFactory
import com.example.inventario.data.InventoryRepository
import com.example.inventario.data.NetworkInventoryRepository
import com.example.inventario.data.SessionStore

class AppContainer(context: Context) {
    private val sessionStore = SessionStore(context.applicationContext)
    private val api = ApiFactory.create(BuildConfig.BASE_URL, sessionStore)

    val repository: InventoryRepository = NetworkInventoryRepository(api, sessionStore)
}
