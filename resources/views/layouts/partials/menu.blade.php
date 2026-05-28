@php
    use App\Models\Menu as MenuModel;
    use App\Support\Permission as Perm;
    use Illuminate\Support\Facades\Route as RouteFacade;
    use Illuminate\Support\Str;

    $user = auth()->user();
    $allowed = $user ? Perm::viewableMenuIds($user) : collect();
    $topMenus = MenuModel::whereNull('parent_id')->where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get();
    $currentRouteName = RouteFacade::currentRouteName();
    $currentBase = $currentRouteName ? Perm::resolveBaseRoute($currentRouteName) : null;

    $wrapperClass = $wrapperClass ?? 'header-menu flex-column flex-lg-row';
    $menuClasses = $menuClasses ?? 'menu menu-lg-rounded menu-column menu-lg-row menu-state-bg menu-title-gray-700 menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-400 fw-bold my-5 my-lg-0 align-items-stretch flex-grow-1';
    $drawerDirection = $drawerDirection ?? 'start';
    $drawerToggle = $drawerToggle ?? '#kt_header_menu_toggle';
    $swapperParent = $swapperParent ?? "{default: '#kt_body', lg: '#kt_header_nav'}";
@endphp

<div class="{{ $wrapperClass }}" data-kt-drawer="true" data-kt-drawer-name="header-menu" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}" data-kt-drawer-direction="{{ $drawerDirection }}" data-kt-drawer-toggle="{{ $drawerToggle }}" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{{ $swapperParent }}">
    <div class="{{ $menuClasses }}" id="kt_header_menu" data-kt-menu="true">
        @foreach($topMenus as $top)
            @php
                $children = MenuModel::where('parent_id', $top->id)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
                $children = $children->filter(fn($c)=> $allowed->contains($c->id));
                $showTop = $allowed->contains($top->id) || $children->isNotEmpty();
                if (!$showTop) {
                    continue;
                }
                $hasChildren = $children->isNotEmpty();
                $isActive = $top->route
                    ? (
                        request()->routeIs($top->route)
                        || ($currentBase && $top->route === $currentBase)
                        || (Str::endsWith($top->route, '.index') && request()->routeIs(Str::beforeLast($top->route, '.index').'.*'))
                      )
                    : $children->contains(function($c) use ($currentBase) {
                        return $c->route && (
                            request()->routeIs($c->route)
                            || ($currentBase && $c->route === $currentBase)
                            || (Str::endsWith($c->route, '.index') && request()->routeIs(Str::beforeLast($c->route, '.index').'.*'))
                        );
                      });
            @endphp

            @if($hasChildren)
                <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="bottom-start" class="menu-item menu-lg-down-accordion me-lg-1">
                    <span class="menu-link py-3 {{ $isActive ? 'active' : '' }}">
                        <span class="menu-title">{{ $top->name }}</span>
                        <span class="menu-arrow d-lg-none"></span>
                    </span>
                    <div class="menu-sub menu-sub-lg-down-accordion menu-sub-lg-dropdown py-4 w-lg-225px">
                        @if($top->route && $allowed->contains($top->id))
                            <div class="menu-item">
                                <a class="menu-link {{ request()->routeIs($top->route) ? 'active' : '' }}" href="{{ route($top->route) }}">
                                    @if($top->icon)
                                        <span class="menu-icon">
                                            <i class="{{ $top->icon }} fs-2 fa-fw"></i>
                                        </span>
                                    @endif
                                    <span class="menu-title">{{ $top->name }}</span>
                                </a>
                            </div>
                        @endif
                        @foreach($children as $child)
                            @php
                                $childActive = $child->route && (
                                    request()->routeIs($child->route)
                                    || ($currentBase && $child->route === $currentBase)
                                    || (Str::endsWith($child->route, '.index') && request()->routeIs(Str::beforeLast($child->route, '.index').'.*'))
                                );
                            @endphp
                            <div class="menu-item">
                                <a class="menu-link {{ $childActive ? 'active' : '' }}" href="{{ $child->route ? route($child->route) : '#' }}">
                                    @if($child->icon)
                                        <span class="menu-icon">
                                            <i class="{{ $child->icon }} fs-2 fa-fw"></i>
                                        </span>
                                    @endif
                                    <span class="menu-title">{{ $child->name }}</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="menu-item me-lg-1">
                    <a class="menu-link py-3 {{ $isActive ? 'active' : '' }}" href="{{ $top->route ? route($top->route) : '#' }}">
                        <span class="menu-title">{{ $top->name }}</span>
                    </a>
                </div>
            @endif
        @endforeach
    </div>
</div>
