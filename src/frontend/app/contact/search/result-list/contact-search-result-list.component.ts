import {
    Component,
    OnInit,
    ViewChild,
    EventEmitter,
    ViewContainerRef,
    OnDestroy,
    TemplateRef,
    Input,
    Output,
    ViewChildren
} from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { MatPaginator } from '@angular/material/paginator';
import { MatSidenav } from '@angular/material/sidenav';
import { MatSort } from '@angular/material/sort';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { startWith, switchMap, map, catchError, takeUntil, tap, filter } from "rxjs/operators";
import { ActivatedRoute, Router } from '@angular/router';
import { HeaderService } from '@service/header.service';
import { Overlay } from '@angular/cdk/overlay';
import { PanelListComponent } from '@appRoot/list/panel/panel-list.component';
import { AppService } from '@service/app.service';
import { BasketHomeComponent } from '@appRoot/basket/basket-home.component';
import { FolderActionListComponent } from '@appRoot/folder/folder-action-list/folder-action-list.component';
import { FoldersService } from '@appRoot/folder/folders.service';
import { FunctionsService } from '@service/functions.service';
import { of, merge, Subject, Subscription, Observable } from 'rxjs';
import { IndexingFieldsService } from '@service/indexing-fields.service';
import { ContactsCriteriaSearchService } from '@service/contactsCriteriaSearch.service';
import { HighlightPipe } from '@plugins/highlight.pipe';
import { ContactFilterToolComponent } from '@appRoot/contact/search/filter-tool/contact-filter-tool.component';
import { PrivilegeService } from '@service/privileges.service';
import { ContactsCriteriaToolComponent } from '@appRoot/contact/search/criteria-tool/contacts-criteria-tool.component';
import { ContactModalComponent } from "@appRoot/administration/contact/modal/contact-modal.component";
import {
    ContactsGroupFormModalComponent
} from "@appRoot/administration/contact/group/form/modal/contacts-group-form-modal.component";
import {FormControl} from "@angular/forms";
import {LatinisePipe} from "ngx-pipes";
import {MatMenuTrigger} from "@angular/material/menu";
import { ContactExportComponent } from "@appRoot/administration/contact/list/export/contact-export.component";

declare let $: any;

@Component({
    selector: 'contact-app-search-result-list',
    templateUrl: 'contact-search-result-list.component.html',
    styleUrls: ['contact-search-result-list.component.scss'],
    providers: [HighlightPipe]
})
export class ContactSearchResultListComponent implements OnInit, OnDestroy {

    @Input() searchTerm: string = '';
    @Input() actionMode: boolean = true;
    @Input() singleSelection: boolean = false;
    @Input() standalone: boolean = false;
    @Input() hideFilter: boolean = false;
    @Input() contactCriteriaTool: ContactsCriteriaToolComponent;
    @Input() sidenavRight: MatSidenav;
    @Input() from: string = '';

    @Output() loadingResult = new EventEmitter<boolean>();

    @ViewChild('filterTemplate', { static: true }) filterTemplate: TemplateRef<any>;
    @ViewChild('toolTemplate', { static: true }) toolTemplate: TemplateRef<any>;
    @ViewChild('panelTemplate', { static: true }) panelTemplate: TemplateRef<any>;
    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild('actionsListContext', { static: false }) actionsList: FolderActionListComponent;
    @ViewChild('appPanelList', { static: false }) appPanelList: PanelListComponent;
    @ViewChild('appFilterToolContactSearch', { static: false }) appFilterToolContactSearch: ContactFilterToolComponent;

    @ViewChild(MatPaginator, { static: true }) paginator: MatPaginator;
    @ViewChild('tableBasketListSort', { static: true }) sort: MatSort;
    @ViewChild('basketHome', { static: true }) basketHome: BasketHomeComponent;

    loading: boolean = true;
    initSearch: boolean = false;
    docUrl: string = '';
    public innerHtml: SafeHtml;
    searchUrl: string = '../rest/searchContacts';
    criteria: any = {};
    contextMenuPosition = { x: '0px', y: '0px' };

    injectDatasParam = {
        resId: 0,
        editable: false
    };
    currentResource: any = {};

    filtersChange = new EventEmitter();

    dragInit: boolean = true;

    dialogRef: MatDialogRef<any>;

    displayedColumns: string[] = ['contact_id'];

    displayedMainData: any = [
        {
            'value': 'contact_id',
            'cssClasses': ['softColorData', 'align_centerData', 'chronoData'],
            'icon': ''
        },
        {
            'value': 'user',
            'cssClasses': ['longData'],
            'icon': ''
        }
    ];

    resultListDatabase: ResultListHttpDao | null;
    data: any = [];
    resultsLength = 0;
    isLoadingResults = false;
    dataFilters: any = {};
    listProperties: any = {};
    currentChrono: string = '';
    currentMode: string = '';

    thumbnailUrl: string = '';
    filterCorrespondentsGroups = new FormControl();
    filteredCorrespondentsGroups: Observable<string[]>;
    correspondentsGroups: any = [];

    selectedContacts: Array<number> = [];
    allResInBasket: number[] = [];

    subscription: Subscription;

    displayColsOrder = [
        { 'id': 'creationDate' },
        { 'id': 'firstname' },
        { 'id': 'lastname' },
        { 'id': 'contactsParameters_company' }
    ];

    templateColumns: number = 7;

    basicConfidentiality: boolean = false;
    advancedConfidentiality: boolean = false;
    basicCustomId: any;
    advancedCustomId: any;
    contactConfidentialityData: any;
    mapping = {
        'getEmail' : 'email',
        'getPhone' : 'phone',
        'getFunction' : 'function',
        'getCivility' : 'civility',
        'getDepartment' : 'department',
        'getCreationDate' : 'creation_date',
        'getModificationDate' : 'modification_date',
        'getAddressNumber' : 'address',
        'getAddressStreet' : 'address',
        'getAddressAdditional1' : 'address',
        'getAddressAdditional2' : 'address',
        'getAddressPostCode' : 'address',
        'getAddressTown' : 'address',
        'getAddressCountry' : 'address',
    };
    paginatorLength: any;
    @ViewChild(MatMenuTrigger, { static: false }) contextMenu: MatMenuTrigger;
    @ViewChildren(MatMenuTrigger) contextMenus: any;
    private destroy$ = new Subject<boolean>();

    constructor(
        private _activatedRoute: ActivatedRoute,
        public translate: TranslateService,
        private router: Router,
        private route: ActivatedRoute,
        public http: HttpClient,
        public dialog: MatDialog,
        private sanitizer: DomSanitizer,
        private latinisePipe: LatinisePipe,
        private headerService: HeaderService,
        public contactsCriteriaSearchService: ContactsCriteriaSearchService,
        private notify: NotificationService,
        public overlay: Overlay,
        public viewContainerRef: ViewContainerRef,
        public appService: AppService,
        public foldersService: FoldersService,
        public functions: FunctionsService,
        public indexingFieldService: IndexingFieldsService,
        public highlightPipe: HighlightPipe,
        public privilegeService: PrivilegeService,
    ) {
        _activatedRoute.queryParams.subscribe(
            params => {
                if (!this.functions.empty(params.value)) {
                    this.searchTerm = params.value;
                    this.initSearch = true;
                    this.criteria = {
                        meta: {
                            values: this.searchTerm
                        }
                    };
                }
            }
        );
    }

    ngOnInit(): void {
        this.http.get('../rest/parameters/contactsConfidentiality').pipe(
            tap((data: any) => {
                this.contactConfidentialityData = data;
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
        if (!this.functions.empty(this.searchTerm)) {
            this.initSearch = true;
            this.criteria = {
                meta: {
                    values: this.searchTerm
                }
            };
        }
        this.headerService.sideBarAdmin = true;

        if (this.toolTemplate !== undefined) {
            this.headerService.initTemplate(this.toolTemplate, this.viewContainerRef, 'toolTemplate');
        }

        if (this.panelTemplate !== undefined && this.sidenavRight !== undefined) {
            this.headerService.initTemplate(this.panelTemplate, this.viewContainerRef, 'panelTemplate');
        }

        if (this.filterTemplate !== undefined && !this.hideFilter) {
            this.headerService.initTemplate(this.filterTemplate, this.viewContainerRef, 'filterTemplateContacts');
        }

        this.listProperties = this.contactsCriteriaSearchService.initListsProperties(this.headerService.user.id);

        if (!this.functions.empty(this.searchTerm)) {
            this.listProperties.criteria = {};
            this.listProperties.criteria.meta = this.criteria.meta;
        }
        this.loading = false;
        this.isLoadingResults = false;
    }

    private _filter(value: string): string[] {
        const filterValue = this.latinisePipe.transform(value.toLowerCase());
        return this.correspondentsGroups.filter((option: any) => this.latinisePipe.transform(this.translate.instant(option['label']).toLowerCase()).includes(filterValue));
    }

    openContactExport() {
        this.dialog.open(ContactExportComponent, {
            panelClass: 'maarch-modal',
            width: '800px',
            autoFocus: false,
            data: {
                selectedContacts: this.selectedContacts
            }
        });
    }

    addContactsToCorrespondentsGroup(groupId: number) {
        const objTosend = this.selectedContacts.map((contactId: any) => ({
            id: contactId,
            type: 'contact'
        }));

        this.http.post('../rest/contactsGroups/' + groupId + '/correspondents', { correspondents: objTosend }).pipe(
            tap(() => {
                this.notify.success('Contact(s) associé(s)');
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getCorrespondentsGroups() {
        this.filterCorrespondentsGroups.reset();
        this.http.get('../rest/contactsGroups').pipe(
            tap((data: any) => {
                this.correspondentsGroups = data['contactsGroups'];
                this.filteredCorrespondentsGroups = this.filterCorrespondentsGroups.valueChanges
                    .pipe(
                        startWith(''),
                        map(state => state ? this._filter(state) : this.correspondentsGroups.slice())
                    );
            })
        ).subscribe();
    }

    openContactsGroupModal() {
        const dialogRef = this.dialog.open(ContactsGroupFormModalComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '99%',
            height: '99%',
            data: {
                contactIds: this.selectedContacts,
                allPerimeters: true
            }
        });
        dialogRef.afterClosed().pipe(
            filter((data: any) => !this.functions.empty(data)),
            tap(async (res: any) => {
                this.refreshDao();
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isConfidential(contact: any, field: string) {
        let userEntityAllowed = true;
        let requiredPrivilege;
        let hiddenFields = [];
        if (contact.basicHiddenFields) {
            contact.basicHiddenFields.forEach((element: any) => {
                if (element === this.mapping[field]) {
                    hiddenFields = contact.basicHiddenFields;
                    requiredPrivilege = this.privilegeService.hasCurrentUserPrivilege('view_confidential_contact');
                    if (contact.basicAllowedEntities && contact.basicAllowedEntities.length > 0) {
                        userEntityAllowed = false;
                        this.headerService.user.entities.forEach((element: any) => {
                            if (contact.basicAllowedEntities.includes(element.entity_id)) {
                                userEntityAllowed = true;
                            }
                        });
                    }
                }
            });
        }

        if (contact.advancedHiddenFields) {
            contact.advancedHiddenFields.forEach((element: any) => {
                if (element === this.mapping[field]) {
                    hiddenFields = contact.advancedHiddenFields;
                    requiredPrivilege = this.privilegeService.hasCurrentUserPrivilege('view_confidential_contact_advanced');
                    if (contact.advancedAllowedEntities && contact.advancedAllowedEntities.length > 0) {
                        userEntityAllowed = false;
                        this.headerService.user.entities.forEach((element: any) => {
                            if (contact.advancedAllowedEntities.includes(element.entity_id)) {
                                userEntityAllowed = true;
                            }
                        });
                    }
                }
            });
        }

        return contact.isPrivate && (!requiredPrivilege || !userEntityAllowed) && hiddenFields.includes(this.mapping[field]);
    }

    isNotConfidential(contact: any, field: string) {
        let userEntityAllowed = true;
        let requiredPrivilege;
        let hiddenFields = [];

        if (contact.basicHiddenFields) {
            contact.basicHiddenFields.forEach((element: any) => {
                if (element === this.mapping[field]) {
                    hiddenFields = contact.basicHiddenFields;
                    requiredPrivilege = this.privilegeService.hasCurrentUserPrivilege('view_confidential_contact');
                    if (contact.basicAllowedEntities && contact.basicAllowedEntities.length > 0) {
                        userEntityAllowed = false;
                        this.headerService.user.entities.forEach((element: any) => {
                            if (contact.basicAllowedEntities.includes(element.entity_id)) {
                                userEntityAllowed = true;
                            }
                        });
                    }
                }
            });
        }

        if (contact.advancedHiddenFields) {
            contact.advancedHiddenFields.forEach((element: any) => {
                if (element === this.mapping[field]) {
                    hiddenFields = contact.advancedHiddenFields;
                    requiredPrivilege = this.privilegeService.hasCurrentUserPrivilege('view_confidential_contact_advanced');
                    if (contact.advancedAllowedEntities && contact.advancedAllowedEntities.length > 0) {
                        userEntityAllowed = false;
                        this.headerService.user.entities.forEach((element: any) => {
                            if (contact.advancedAllowedEntities.includes(element.entity_id)) {
                                userEntityAllowed = true;
                            }
                        });
                    }
                }
            });
        }

        return !contact.isPrivate || requiredPrivilege && userEntityAllowed || !hiddenFields.includes(this.mapping[field]);
    }

    initSavedCriteria() {
        if (Object.keys(this.listProperties.criteria).length > 0) {
            const obj = { query: [] };
            Object.keys(this.listProperties.criteria).forEach(key => {
                const objectItem = {};
                objectItem['identifier'] = key;
                objectItem['values'] = this.listProperties.criteria[key].values;
                obj.query.push(objectItem);
            });
            this.contactCriteriaTool.selectSearchTemplate(obj, false);
            this.criteria = this.listProperties.criteria;
            if (!this.functions.empty(this.listProperties.filters)) {
                this.dataFilters = this.listProperties.filters;
            }
            this.initResultList();
        } else { // EDISSYUM - NCH01 Affichage des documents même si aucun mots clés n'est donné - Remplacement du else if par un simple else
            this.initResultList();
        }
    }

    ngOnDestroy() {
        this.destroy$.next(true);
    }

    launch(row: any) {
        const thisSelect = { checked: true };
        const thisDeselect = { checked: false };

        if (this.actionMode) {
            row.checked = true;
            this.toggleAllRes(thisDeselect);
            this.toggleRes(thisSelect, row);
            this.openContact(row);
            // this.router.navigate([`/administration/contacts/list/${row.contact_id}`], {queryParams: {fromSearch: true}});
        } else {
            row.checked = !row.checked;
            this.toggleRes(row.checked ? thisSelect : thisDeselect, row);
        }
    }

    openContact(contact: any = null) {
        const dialogRef = this.dialog.open(ContactModalComponent, {
            maxWidth: '100vw',
            width: 'auto',
            panelClass: 'maarch-modal',
            disableClose: true,
            data: {
                isPrivate: contact !== null ? contact.isPrivate : null,
                editMode: false,
                contactId: contact.contact_id !== null ? contact.contact_id : null,
                contactType: 'contact'
            }
        });

        dialogRef.afterClosed().pipe(
            filter((data: number) => data !== undefined),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    launchSearch(criteria: any = this.criteria, initSearch = false) {
        this.listProperties.page = 0;
        this.listProperties.pageSize = 0;
        if (initSearch) {
            this.dataFilters = {};
        }
        this.criteria = JSON.parse(JSON.stringify(criteria));

        const regex = /contactCustomField_[.]*/g;
        Object.keys(this.criteria).forEach((element: any) => {
            if (regex.test(element)) {
                if(Array.isArray(this.criteria[element]['values'])) {
                    this.criteria[element]['values'].forEach((values: any) => {
                        values['id'] = values['label'];
                    });
                }
            }
        });

        if (!this.initSearch) {
            this.initResultList();
            this.initSearch = true;
        } else {
            this.refreshDao();
        }
    }


    initResultList() {
        this.resultListDatabase = new ResultListHttpDao(this.http, this.contactsCriteriaSearchService);
        // If the user changes the sort order, reset back to the first page.
        this.paginator.pageIndex = this.listProperties.page;
        this.paginator.pageSize = this.listProperties.pageSize;
        this.sort.sortChange.subscribe(() => this.paginator.pageIndex = 0);
        // When list is refresh (sort, page, filters)

        const regex = /contactCustomField_[.]*/g;
        Object.keys(this.criteria).forEach((element: any) => {
            if (regex.test(element)) {
                if(Array.isArray(this.criteria[element]['values'])) {
                    this.criteria[element]['values'].forEach((values: any) => {
                        values['id'] = values['label'];
                    });
                }
            }
        });
        merge(this.sort.sortChange, this.paginator.page, this.filtersChange)
            .pipe(
                takeUntil(this.destroy$),
                startWith({}),
                switchMap(() => {
                    if (!this.isLoadingResults) {
                        // To Reset scroll
                        this.data = [];
                        if (this.sidenavRight !== undefined) {
                            this.sidenavRight.close();
                        }
                        this.isLoadingResults = true;
                        this.loadingResult.emit(true);
                        return this.resultListDatabase!.getRepoIssues(
                            this.sort.active, this.sort.direction, this.paginator.pageIndex, this.searchUrl, this.listProperties, this.paginator.pageSize, this.criteria, this.dataFilters);
                    } else {
                        /**
                         * To resolve the error :
                         * You provided 'undefined' where a stream was expected. You can provide an Observable, Promise, Array, or Iterable
                         */
                        return new Observable<BasketList>();
                    }
                }),
                map((data: any) => {
                    this.selectedContacts = [];
                    // Flip flag to show that loading has finished.
                    this.isLoadingResults = false;
                    this.loadingResult.emit(false);
                    data = this.processPostData(data);
                    this.templateColumns = data.templateColumns;
                    this.dataFilters = data.filters;
                    this.contactsCriteriaSearchService.updateListsPropertiesFilters(data.filters);
                    this.resultsLength = data.count;
                    this.paginatorLength = data.count > 10000 ? 10000 : data.count;
                    this.allResInBasket = data.allContacts;
                    data.contacts.forEach((contact: any) => {
                        contact.isPrivate = false;
                        if (this.contactConfidentialityData.parameter) {
                            const param = JSON.parse(this.contactConfidentialityData.parameter.param_value_string);
                            this.basicCustomId = param.basic.customId;
                            this.advancedCustomId = param.advanced.customId;
                            const basicAllowedField = param.basic.hiddenFields.replace(/\s/g, "").split(',');
                            const advancedAllowedField = param.advanced.hiddenFields.replace(/\s/g, "").split(',');
                            const basicAllowedEntities = param.basic.entitiesAllowed.replace(/\s/g, "").split(',');
                            const advancedAllowedEntities = param.advanced.entitiesAllowed.replace(/\s/g, "").split(',');
                            contact.basicHiddenFields = [];
                            contact.basicAllowedEntities = [];
                            contact.advancedHiddenFields = [];
                            contact.advancedAllowedEntities = [];
                            if (contact.customFields !== null) {
                                Object.keys(contact.customFields).forEach((customFieldCpt: any) => {
                                    const customField = {
                                        id: customFieldCpt,
                                        value: contact.customFields[customFieldCpt]
                                    };
                                    if ((customField.id === this.basicCustomId || customField.id === this.advancedCustomId) && customField.value === 'Oui') {
                                        contact.isPrivate = true;
                                        if (customField.id === this.basicCustomId) {
                                            this.basicConfidentiality = true;
                                            basicAllowedField.forEach((element: any) => {
                                                contact.basicHiddenFields.push(element);
                                            });
                                            basicAllowedEntities.forEach((element: any) => {
                                                if (element && element !== '*') {
                                                    contact.basicAllowedEntities.push(element);
                                                }
                                            });
                                        }
                                        if (customField.id === this.advancedCustomId) {
                                            this.advancedConfidentiality = true;
                                            advancedAllowedField.forEach((element: any) => {
                                                contact.advancedHiddenFields.push(element);
                                            });
                                            advancedAllowedEntities.forEach((element: any) => {
                                                if (element && element !== '*') {
                                                    contact.advancedAllowedEntities.push(element);
                                                }
                                            });
                                        }
                                    }
                                });
                            }
                        }
                    });
                    return data.contacts;
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    this.selectedContacts = [];
                    this.data = [];
                    this.resultsLength = 0;
                    this.paginatorLength = 0;
                    this.dataFilters = {};
                    this.allResInBasket = [];
                    this.isLoadingResults = false;
                    this.loadingResult.emit(false);
                    this.initSearch = false;
                    return of(false);
                })
            ).subscribe(data => this.data = data);
    }

    goTo(row: any) {
        // this.contactsCriteriaSearchService.filterMode = false;
        if (this.docUrl === '../rest/resources/' + row.resId + '/content' && this.sidenavRight.opened) {
            this.sidenavRight.close();
        } else {
            this.docUrl = '../rest/resources/' + row.resId + '/content';
            this.currentChrono = row.chrono;
            this.innerHtml = this.sanitizer.bypassSecurityTrustHtml(
                '<iframe style=\'height:100%;width:100%;\' src=\'' + this.docUrl + '\' class=\'embed-responsive-item\'>' +
                '</iframe>');
            this.sidenavRight.open();
        }
    }

    goToDetail(row: any) {
        this.router.navigate([`/resources/${row.resId}`], {queryParams: {fromSearch: true}});
    }

    goToFolder(folder: any) {
        this.router.navigate([`/folders/${folder.id}`]);
    }

    togglePanel(mode: string, row: any) {
        const thisSelect = { checked: true };
        const thisDeselect = { checked: false };
        row.checked = true;
        this.toggleAllRes(thisDeselect);
        this.toggleRes(thisSelect, row);

        if (this.currentResource.resId === row.resId && this.sidenavRight.opened && this.currentMode === mode) {
            this.sidenavRight.close();
        } else {
            this.currentMode = mode;
            this.currentResource = row;
            this.appPanelList.loadComponent(mode, row);
            this.sidenavRight.open();
        }
    }

    refreshBadgeNotes(nb: number) {
        this.currentResource.countNotes = nb;
    }

    refreshBadgeAttachments(nb: number) {
        this.currentResource.countAttachments = nb;
    }

    refreshDao() {
        this.paginator.pageIndex = this.listProperties.page;
        this.filtersChange.emit();
    }

    refreshDaoAfterAction() {
        this.sidenavRight.close();
        this.refreshDao();
        const e: any = { checked: false };
        this.toggleAllRes(e);
    }

    viewThumbnail(row: any) {
        if (row.hasDocument) {
            this.thumbnailUrl = '../rest/resources/' + row.resId + '/thumbnail';
            $('#viewThumbnail').show();
            $('#listContent').css({ 'overflow': 'hidden' });
        }
    }

    closeThumbnail() {
        $('#viewThumbnail').hide();
        $('#listContent').css({ 'overflow': 'auto' });
    }

    getField(field: any) {
        const icon = {
            'phone': {'icon': 'fa-phone', 'label': this.translate.instant('lang.contactsParameters_phone')},
            'company': {'icon': 'fa-user', 'label': this.translate.instant('lang.contactsParameters_company')},
            'function': {'icon': 'fa-user', 'label': this.translate.instant('lang.contactsParameters_function')},
            'lastname': {'icon': 'fa-user', 'label': this.translate.instant('lang.contactsParameters_lastname')},
            'firstname': {'icon': 'fa-user', 'label': this.translate.instant('lang.contactsParameters_firstname')},
            'email': {'icon': 'fa-envelope', 'label': this.translate.instant('lang.contactsParameters_email')},
            'department': {'icon': 'fa-user', 'label': this.translate.instant('lang.contactsParameters_department')},
            'creationDate': {'icon': 'fa-calendar', 'label': this.translate.instant('lang.creationDate')},
            'contactsGroups': {'icon': 'fa-users', 'label': this.translate.instant('lang.contact_contactsGroups')},
            'modificationDate': {'icon': 'fa-calendar', 'label': this.translate.instant('lang.getModificationDate')},
            'addresstown': {'icon': 'fa-map-marker-alt', 'label': this.translate.instant('lang.entitiesParameters_addressTown')},
            'addressnumber': {'icon': 'fa-map-marker-alt', 'label': this.translate.instant('lang.entitiesParameters_addressNumber')},
            'addressstreet': {'icon': 'fa-map-marker-alt', 'label': this.translate.instant('lang.entitiesParameters_addressStreet')},
            'addresscountry': {'icon': 'fa-map-marker-alt', 'label': this.translate.instant('lang.entitiesParameters_addressCountry')},
            'addresspostcode': {'icon': 'fa-map-marker-alt', 'label': this.translate.instant('lang.entitiesParameters_addressPostcode')}
        };

        let fieldIcon = {icon: 'fa-hashtag', label : ''};
        if (icon[field]) {
            fieldIcon = icon[field];
        }
        return fieldIcon;
    }

    processPostData(data: any) {
        data.contacts.forEach((element: any) => {
            // Process main datas
            Object.keys(element).forEach((key) => {
                if (Object.keys(this.criteria).indexOf(key) > -1) {
                    element[key] = this.highlightPipe.transform(element[key], this.criteria[key].values);
                }
            });
            // Process secondary datas
            element.display.forEach((key: any) => {
                key.event = false;
                key.displayTitle = key.displayValue;
                if ((key.displayValue == null || key.displayValue === '')) {
                    key.displayValue = this.translate.instant('lang.undefined');
                    key.displayTitle = '';
                }

                const regex = /contactCustomField_[.]*/g;
                if (key.value.match(regex)) {
                    key.displayLabel = key.label;
                }

                key.label = key.displayLabel === undefined ? this.translate.instant('lang.' + key.value) : key.displayLabel;

                // HighLight sub data
                key.displayValue = this.setHighLightData(key);
            });
            element['checked'] = this.selectedContacts.indexOf(element['contactId']) !== -1;
        });
        return data;
    }

    setHighLightData(data: any) {
        const regex = /contactCustomField_[.]*/g;

        const mappingdata = {
            getEmail: 'email',
            getPhone: 'phone',
            getCivility: 'civility',
            getAddressTown: 'addresstown',
            getCreationDate: 'creationDate',
            getAddressNumber: 'addressnumber',
            getAddressStreet: 'addressstreet',
            getAddressCountry: 'addresscountry',
            getModificationDate: 'modificationDate',
            getAddressAdditional1: 'addressadditional1',
            getAddressAdditional2: 'addressadditional2'
        };

        if (Object.keys(this.criteria).indexOf(mappingdata[data.value]) > -1) {
            if (Array.isArray(this.criteria[mappingdata[data.value]].values)) {
                this.criteria[mappingdata[data.value]].values.forEach((val: any) => {
                    data.displayValue = this.highlightPipe.transform(data.displayValue, !this.functions.empty(val.label) ? val.label.replace(/&nbsp;/g, '') : val);
                });
            } else {
                data.displayValue = this.highlightPipe.transform(data.displayValue, this.criteria[mappingdata[data.value]].values);
            }
        } else if (data.value.match(regex) !== null && Object.keys(this.criteria).indexOf(data.value) > -1) {
            if (Array.isArray(this.criteria[data.value].values)) {
                this.criteria[data.value].values.forEach((val: any) => {
                    data.displayValue = this.highlightPipe.transform(data.displayValue, val.label.replace(/&nbsp;/g, ''));
                });
            } else {
                data.displayValue = this.highlightPipe.transform(data.displayValue, this.criteria[data.value].values);
            }
        }
        return data.displayValue;
    }

    returnHighlighted(id, value) {
        const mappingdata = {
            getCreationDate: 'creationDate',
            getModificationDate: 'modificationDate',
        };
        if (mappingdata[id] !== undefined && Object.keys(this.criteria).indexOf(mappingdata[id]) > -1) {
            value = '<span class=\'highlightResult\'>' + value + '</span>';
        }
        return value;
    }

    toggleRes(e: any, row: any) {
        if (this.singleSelection) {
            this.toggleAllRes({ checked: false });
        }
        if (e.checked) {
            if (this.selectedContacts.indexOf(row.contact_id) === -1) {
                this.selectedContacts.push(row.contact_id);
                row.checked = true;
            }
        } else {
            const index = this.selectedContacts.indexOf(row.contact_id);
            this.selectedContacts.splice(index, 1);
            row.checked = false;
        }
    }

    toggleAllRes(e: any) {
        this.selectedContacts = [];
        if (e.checked) {
            this.data.forEach((element: any) => {
                element['checked'] = true;
            });
            this.selectedContacts = JSON.parse(JSON.stringify(this.allResInBasket));
        } else {
            this.data.forEach((element: any) => {
                element['checked'] = false;
            });
        }
    }

    selectSpecificRes(row: any) {
        const thisSelect = { checked: true };
        const thisDeselect = { checked: false };

        this.toggleAllRes(thisDeselect);
        this.toggleRes(thisSelect, row);
    }

    open({ x, y }: MouseEvent, row: any) {
        const thisSelect = { checked: true };
        const thisDeselect = { checked: false };
        if (row.checked === false) {
            row.checked = true;
            this.toggleAllRes(thisDeselect);
            this.toggleRes(thisSelect, row);
        }
        this.contextMenuPosition.x = x + 'px';
        this.contextMenuPosition.y = y + 'px';
        this.contextMenus.toArray()[this.contextMenus.toArray().map((item: any) => item._element.nativeElement.id).indexOf('menuButtonContext')].openMenu();

        // prevents default
        return false;
    }

    listTodrag() {
        return this.foldersService.getDragIds();
    }

    emptyCriteria() {
        return Object.keys(this.criteria).length === 0;
    }

    isArrayType(value: any) {
        return (Array.isArray(value));
    }

    removeCriteria(identifier: string, value: any = null) {
        if (!this.isLoadingResults) {
            this.contactCriteriaTool.toggleTool(true);
            if (identifier !== '_ALL') {
                if (value === null || this.criteria[identifier].values.length === 1) {
                    this.criteria[identifier].values = [];
                } else {
                    const indexArr = this.criteria[identifier].values.indexOf(value);
                    this.criteria[identifier].values.splice(indexArr, 1);
                }
                this.contactCriteriaTool.resetCriteria(identifier, value);
            } else {
                Object.keys(this.criteria).forEach(key => {
                    this.criteria[key].values = [];
                });
                this.contactCriteriaTool.resetAllCriteria();
            }
        }
    }

    updateFilters() {
        this.listProperties.page = 0;

        this.contactsCriteriaSearchService.updateListsProperties(this.listProperties);

        this.refreshDao();
    }

    changeOrderDir() {
        if (this.listProperties.orderDir === 'ASC') {
            this.listProperties.orderDir = 'DESC';
        } else {
            this.listProperties.orderDir = 'ASC';
        }
        this.updateFilters();
    }

    getselectedContactsources() {
        return this.selectedContacts;
    }
}
export interface BasketList {
    folder: any;
    resources: any[];
    countResources: number;
    allContacts: number[];
    filter: any[];
}

export class ResultListHttpDao {

    constructor(private http: HttpClient, private contactsCriteriaSearchService: ContactsCriteriaSearchService) { }

    getRepoIssues(sort: string, order: string, page: number, href: string, filters: any, pageSize: number, criteria: any, sideFilters: any): Observable<BasketList> {
        this.contactsCriteriaSearchService.updateListsPropertiesPage(page);
        this.contactsCriteriaSearchService.updateListsPropertiesPageSize(pageSize);
        this.contactsCriteriaSearchService.updateListsPropertiesCriteria(criteria);
        const offset = page * pageSize;
        const requestUrl = `${href}?limit=${pageSize}&offset=${offset}&order=${filters.order}&orderDir=${filters.orderDir}`;
        let dataToSend = Object.assign({}, this.contactsCriteriaSearchService.formatDatas(JSON.parse(JSON.stringify(criteria))), { filters: sideFilters });
        dataToSend = {
            ... dataToSend,
        };
        return this.http.post<BasketList>(requestUrl, dataToSend);
    }
}
