# Introduction & Architectural Philosophy

### Why Laravel Smart Upload?

| Feature | Native Laravel `store()` | Spatie MediaLibrary | Intervention Image | **Laravel Smart Upload** |
| :--- | :---: | :---: | :---: | :---: |
| **Database Requirement** | None | Requires Migrations & Tables | None | **Zero Database Dependency** |
| **Fluent Chaining (DX)** | Primitive | Model-Coupled | Primitive | **Full Method Chaining** |
| **Automatic WebP Conversion** | No | Manual Setup | Manual Coding | **Built-in & Configurable** |
| **Aspect-Ratio `scaleDown()`** | No | Requires Conversions | Manual Math | **Built-in Intent** |
| **Center-Crop `fit()`** | No | Requires Conversions | Manual Math | **Built-in Intent** |
| **Old File Auto-Purge** | Manual `Storage::delete` | Semi-Automatic | No | **Built-in `->replace()` & Trait** |
| **Atomic DB Rollback Shield** | No (Ghost files stay on S3) | No | No | **Auto-Purge on DB Rollback** |
| **Resumable Chunk Engine** | No | No | No | **Built-in Stream Chunker** |
| **Mobile & Two-Step Upload** | No | Complex | No | **Built-in `temp()` / `fromTemp()`** |
| **MIME Magic-Bytes Security** | Basic | Basic | None | **Strict Guard by Default** |
| **SVG XSS Sanitizer** | No | No | No | **Built-in Threat Inspection** |

---

### Core Philosophy

1. **Zero Database Clutter**: Files belong to the storage disk; database tables should store application state, not low-level media pointers unless explicitly required.
2. **Order-Independent Chaining**: Fluent methods can be invoked in any sequence without unexpected side effects.
3. **Transaction Safety**: Files created inside database transactions must never remain if the database rolls back.
4. **Performance & Low Memory**: Large files and videos must be streamed and merged with minimal RAM utilization.

