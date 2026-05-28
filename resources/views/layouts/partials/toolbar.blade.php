<div class="toolbar py-5 py-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-xxl d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-3">
            <h1 class="d-flex text-dark fw-bolder my-1 fs-3">
                @yield('page_title', trim($__env->yieldContent('title')) ?: 'Dashboard')
            </h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold text-gray-600 fs-7 my-1">
                @hasSection('page_breadcrumbs')
                    @yield('page_breadcrumbs')
                @else
                    <li class="breadcrumb-item text-gray-600">
                        <a href="{{ route('admin.dashboard') }}" class="text-gray-600 text-hover-primary">Admin</a>
                    </li>
                    <li class="breadcrumb-item text-gray-500">
                        @yield('page_title', trim($__env->yieldContent('title')) ?: 'Dashboard')
                    </li>
                @endif
            </ul>
        </div>
        @hasSection('page_actions')
            <div class="d-flex align-items-center py-2 py-md-1">
                @yield('page_actions')
            </div>
        @endif
    </div>
</div>
