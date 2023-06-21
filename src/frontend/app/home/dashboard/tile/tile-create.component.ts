import { Component, Inject, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { DashboardService } from '../dashboard.service';
import { FunctionsService } from '@service/functions.service';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { catchError, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { ColorEvent } from 'ngx-color';
import { PrivilegeService } from '@service/privileges.service';
import { SortPipe } from '@plugins/sorting.pipe';
import { FormControl } from '@angular/forms';

@Component({
    templateUrl: 'tile-create.component.html',
    styleUrls: ['tile-create.component.scss'],
    providers: [DashboardService, SortPipe]
})
export class TileCreateComponent implements OnInit {

    loading: boolean = false;

    tileTypes: any[] = [];
    views: any[] = [];
    baskets: any[] = [];
    folders: any[] = [];
    chartTypes: any[] = [];
    chartModes: any[] = [];
    searchTemplates: any[] = [];
    searchTemplatesControl: FormControl = new FormControl();

    menus: any[] = [];
    menusControl: FormControl = new FormControl();

    position: string = null;
    tileLabel: string = null;
    tileOtherInfos: any = {};
    selectedTileType: string = null;
    selectedView: string = null;
    selectedColor: string = '#a5d6a7'; // EDISSYUM - NCH01 Changement des couleurs de Maarch
    extraParams: any = {};

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<TileCreateComponent>,
        public dashboardService: DashboardService,
        public functionsService: FunctionsService,
        private notify: NotificationService,
        public headerService: HeaderService,
        public privilegeService: PrivilegeService,
        private sortPipe: SortPipe,
    ) { }

    ngOnInit(): void {
        this.position = this.data.position;
        this.getTileTypes();
    }

    getTileTypes() {
        const tmpTileTypes = this.dashboardService.getTileTypes();
        this.tileTypes = tmpTileTypes.map((tileType: any) => ({
            id: tileType,
            label: this.translate.instant('lang.' + tileType)
        }));
    }

    getViews() {
        this.tileLabel = this.translate.instant('lang.' + this.selectedTileType);
        const tmpViews = this.dashboardService.getViewsByTileType(this.selectedTileType);
        this.views = tmpViews.map((view: any) => ({
            ...view,
            label: this.translate.instant('lang.' + view.id)
        }));
        this.selectedView = this.views.length > 0 ? this.views[0].id : null;

        if (this.selectedTileType === 'basket') {
            this.getBaskets();
        } else if (this.selectedTileType === 'folder') {
            this.getFolders();
        } else if (this.selectedTileType === 'shortcut') {
            this.getAdminMenu();
        } else if (this.selectedTileType === 'externalSignatoryBook') {
            this.getMPInfos();
        } else if (this.selectedTileType === 'searchTemplate') {
            this.getSearchTemplates();
        }
    }

    getChartTypes() {
        if (this.chartTypes.length === 0) {
            this.chartTypes = this.dashboardService.getChartTypes();
            this.chartTypes = this.chartTypes.map((type: any) => ({
                ...type,
                label: this.translate.instant('lang.chart_' + type.type)
            }));
        }
    }

    setChartModes() {
        this.chartModes = this.dashboardService.getChartModes(this.extraParams.chartType);
    }

    getBaskets() {
        if (this.baskets.length === 0) {
            this.http.get('../rest/home').pipe(
                tap((data: any) => {
                    if (data.regroupedBaskets.length > 0) {
                        this.baskets = data.regroupedBaskets;
                        this.tileLabel = `${this.baskets[0].baskets[0].basket_name} (${this.baskets[0].groupDesc})`;
                        this.extraParams = {
                            groupId: this.baskets[0].groupSerialId,
                            basketId: this.baskets[0].baskets[0].id
                        };
                    } else {
                        this.notify.error(this.translate.instant('lang.noAvailableBasket'));
                        this.resetData();
                    }
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    getMPInfos() {
        this.http.get('../rest/home').pipe(
            tap((data: any) => {
                if (!data.isLinkedToMaarchParapheur) {
                    this.notify.error(this.translate.instant('lang.acountNotLinkedTomaarchParapheur'));
                    this.resetData();
                } else {
                    this.tileOtherInfos = {
                        maarchParapheurUrl: data.maarchParapheurUrl
                    };
                }
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getSearchTemplates() {
        if (this.searchTemplates.length === 0) {
            this.http.get('../rest/searchTemplates').pipe(
                tap((data: any) => {
                    if (data.searchTemplates.length > 0) {
                        this.searchTemplates = data.searchTemplates;
                        this.setSearchTemplate(this.searchTemplates[0]);
                        this.searchTemplatesControl.setValue(this.searchTemplates[0]);
                    } else {
                        this.notify.error(this.translate.instant('lang.noSearchTemplate'));
                        this.resetData();
                    }
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    setSearchTemplate(searchTemplate: any) {
        this.extraParams = {
            searchTemplateId: searchTemplate.id,
        };
        this.tileLabel = searchTemplate.label;
    }

    getFolders() {
        if (this.folders.length === 0) {
            this.http.get('../rest/pinnedFolders').pipe(
                tap((data: any) => {
                    if (data.folders.length > 0) {
                        this.folders = data.folders;
                        this.tileLabel = `${this.folders[0].name}`;
                        this.extraParams = {
                            folderId: this.folders[0].id,
                        };
                    } else {
                        this.notify.error(this.translate.instant('lang.noPinnedFolder'));
                        this.resetData();
                    }
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    resetData() {
        this.selectedTileType = null;
        this.selectedView = null;
        this.views = [];
    }

    getAdminMenu() {
        if (this.menus.length === 0) {
            let arrMenus: any[] = [];
            let tmpMenus: any;
            tmpMenus = this.privilegeService.getCurrentUserMenus().map((menu: any) => ({
                ...menu,
                label: this.translate.instant(menu.label)
            }));
            tmpMenus = this.sortPipe.transform(tmpMenus, 'label');

            if (tmpMenus.length > 0) {
                tmpMenus.unshift({
                    id: 'opt_menu',
                    label: '&nbsp;&nbsp;&nbsp;&nbsp;' + this.translate.instant('lang.menu'),
                    title: this.translate.instant('lang.menu'),
                    color: '#00000',
                    disabled: true,
                    isTitle: true
                });
                arrMenus = arrMenus.concat(tmpMenus);
            }

            tmpMenus = this.privilegeService.getAdministrations(this.headerService.user.privileges).map((menu: any) => ({
                ...menu,
                label: this.translate.instant(menu.label)
            }));
            tmpMenus = this.sortPipe.transform(tmpMenus, 'label');
            if (tmpMenus.length > 0) {
                tmpMenus.unshift({
                    id: 'opt_admin',
                    label: '&nbsp;&nbsp;&nbsp;&nbsp;' + this.translate.instant('lang.administration'),
                    title: this.translate.instant('lang.administration'),
                    color: '#00000',
                    disabled: true,
                    isTitle: true
                });
                arrMenus = arrMenus.concat(tmpMenus);
            }
            if (arrMenus.length > 0) {
                this.menus = arrMenus;
                this.setMenu(this.menus[1]);
                this.menusControl.setValue(this.menus[1]);
            } else {
                this.notify.error(this.translate.instant('lang.noDataAvailable'));
                this.resetData();
            }
        }
    }

    setBasket(data: any) {
        this.tileLabel = `${data.basketName} (${data.groupName})`;
        this.extraParams = {
            basketId: data.basketId,
            groupId: data.groupId
        };
    }

    setIndexingGroup(data: any) {
        this.tileLabel = `${this.menusControl.value.label} (${data.label})`;
        this.extraParams.groupId = data.id;
        this.tileOtherInfos.privRoute = `/indexing/${this.extraParams.groupId}`;
    }

    setMenu(menu: any) {
        this.extraParams = {};
        this.extraParams.privilegeId = menu.id;
        this.tileLabel = menu.label;
        this.tileOtherInfos = {
            icon: menu.style,
            privRoute: menu.route,
        };
        if (menu.id === 'indexing') {
            this.extraParams.groupId = menu.groups[0].id;
            this.tileLabel =  menu.label + ' (' + menu.groups[0].label + ')';
        }
    }

    compareBaskets(basket1: any, basket2: any) {
        return (basket1.groupId === basket2.groupId && basket1.basketId === basket2.basketId);
    }

    compareGroups(groupInSelect: any, currentGroupId: any) {
        return (groupInSelect.id === currentGroupId);
    }

    compareFolders(folder1: any, folder2: any) {
        return (folder1.folderId === folder2.folderId);
    }

    compareMenus(menu1: any, menu2: any) {
        return (menu1.id === menu2.id);
    }

    isValid() {
        return !this.functionsService.empty(this.position) && !this.functionsService.empty(this.selectedTileType) && ((this.views.length > 0 && !this.functionsService.empty(this.selectedView)) || this.views.length === 0);
    }

    formatData() {
        return {
            type: this.selectedTileType,
            view: this.selectedView,
            userId: this.headerService.user.id,
            position: this.position,
            color: this.selectedColor,
            parameters: this.extraParams
        };
    }

    resetExtraParams() {
        if (this.selectedView === 'chart') {
            this.getChartTypes();
            this.extraParams['chartType'] = 'pie';
            this.extraParams['chartMode'] = 'doctype';
            this.setChartModes();
        } else {
            delete this.extraParams.chartMode;
        }
    }

    handleChange($event: ColorEvent) {
        this.selectedColor = $event.color.hex;
    }

    onSubmit() {
        const objToSend: any = this.formatData();
        this.http.post('../rest/tiles', objToSend).pipe(
            tap((data: any) => {
                this.dialogRef.close(data.id);
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
