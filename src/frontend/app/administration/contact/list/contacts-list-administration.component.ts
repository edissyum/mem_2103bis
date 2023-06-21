import { Component, OnInit, ViewChild, EventEmitter, Inject, TemplateRef, ViewContainerRef, ViewChildren } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { MatSidenav } from '@angular/material/sidenav';
import { AppService } from '@service/app.service';
import { Observable, merge, Subject, of as observableOf, of } from 'rxjs';
import { MatDialog, MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { MatPaginator } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';
import { takeUntil, startWith, switchMap, map, catchError, filter, exhaustMap, tap, debounceTime, distinctUntilChanged } from 'rxjs/operators';
import { ConfirmComponent } from '../../../../plugins/modal/confirm.component';
import { FormControl } from '@angular/forms';
import { FunctionsService } from '@service/functions.service';
import { ContactExportComponent } from './export/contact-export.component';
import { AdministrationService } from '../../../../app/administration/administration.service';
import { ContactImportComponent } from './import/contact-import.component';
import { SelectionModel } from '@angular/cdk/collections';
import { ContactsGroupFormModalComponent } from '../group/form/modal/contacts-group-form-modal.component';
import { MatMenuTrigger } from '@angular/material/menu';
import { LatinisePipe } from 'ngx-pipes';
import { ContactService } from '@service/contact.service';
import { PrivilegeService } from "@service/privileges.service"; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts

@Component({
    selector: 'contact-list',
    templateUrl: 'contacts-list-administration.component.html',
    styleUrls: ['contacts-list-administration.component.scss'],
    providers: [ContactService]
})
export class ContactsListAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;
    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;

    subMenus: any[] = [];

    loading: boolean = false;

    filtersChange = new EventEmitter();

    data: any;

    displayedColumnsContact: string[] = ['filling', 'firstname', 'lastname', 'company', 'formatedAddress', 'actions'];

    isLoadingResults = true;
    allContacts: any = [];
    routeUrl: string = '../rest/contacts';
    resultListDatabase: ContactListHttpDao | null;
    resultsLength = 0;
    correspondentsGroups: any = [];
    selection = new SelectionModel<Element>(true, []);

    searchContact = new FormControl();
    search: string = '';
    dialogRef: MatDialogRef<any>;
    filterCorrespondentsGroups = new FormControl();
    filteredCorrespondentsGroups: Observable<string[]>;

    customFields: any[] = []; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    showSubMenu: boolean = true; // EDISSYUM - NCH01 Rajout de la possibilité de visualiser les contacts sans droits d'administration

    @ViewChild(MatPaginator, { static: true }) paginator: MatPaginator;
    @ViewChild('tableContactListSort', { static: true }) sort: MatSort;

    private destroy$ = new Subject<boolean>();

    contextMenuPosition = { x: '0px', y: '0px' };

    @ViewChild(MatMenuTrigger, { static: false }) contextMenu: MatMenuTrigger;
    @ViewChildren(MatMenuTrigger) contextMenus: any;


    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public dialog: MatDialog,
        public functions: FunctionsService,
        private latinisePipe: LatinisePipe,
        public adminService: AdministrationService,
        public contactService: ContactService,
        private viewContainerRef: ViewContainerRef,
        public functionsService: FunctionsService, // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
        public privilegeService: PrivilegeService // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    ) {
        this.subMenus = contactService.getAdminMenu();
        // EDISSYUM - NCH01 Rajout de la possibilité de visualiser les contacts sans droits d'administration
        let adminContact = false;
        this.headerService.user.privileges.forEach((element: any) => {
            if (element === 'admin_contacts') {
                adminContact = true;
            }
        });

        if (!adminContact) {
            this.showSubMenu = false;
        }

        if (!this.showSubMenu) {
            this.subMenus = [{
                icon: 'fa fa-book',
                route: '/administration/contacts',
                label: this.translate.instant('lang.contactsList'),
                current: true
            }];
        }
        // END EDISSYUM - NCH01
    }


    async ngOnInit(): Promise<void> { // EDISSYUM - NCH01 Rajout de la confidentialité des contacts ajout du async
        await this.getCustomFields(); // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');
        this.loading = true;
        this.adminService.setAdminId('admin_contacts_list');
        if (this.functions.empty(this.adminService.getFilter())) {
            this.adminService.saveDefaultFilter();
        }
        this.initContactList();

        this.initAutocompleteContacts();
    }

    initContactList() {
        this.resultListDatabase = new ContactListHttpDao(this.http);
        this.paginator.pageIndex = this.adminService.getFilter('page');
        this.sort.active = this.adminService.getFilter('sort');
        this.sort.direction = this.adminService.getFilter('sortDirection');
        this.sort.sortChange.subscribe(() => this.paginator.pageIndex = 0);

        // When list is refresh (sort, page, filters)
        merge(this.sort.sortChange, this.paginator.page, this.filtersChange)
            .pipe(
                takeUntil(this.destroy$),
                startWith({}),
                switchMap(() => {
                    this.adminService.saveFilter(
                        {
                            sort: this.sort.active,
                            sortDirection: this.sort.direction,
                            page: this.paginator.pageIndex,
                            field: this.adminService.getFilter('field')
                        }
                    );
                    // this.searchContact.setValue(this.adminService.getFilter('field'));
                    this.search = this.adminService.getFilter('field');
                    this.isLoadingResults = true;
                    return this.resultListDatabase!.getRepoIssues(
                        this.sort.active, this.sort.direction, this.paginator.pageIndex, this.routeUrl, this.search);
                }),
                map(data => {
                    this.isLoadingResults = false;
                    data = this.processPostData(data);
                    this.resultsLength = data.count;
                    this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.contacts').toLowerCase(), '', '');
                    return data.contacts;
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    this.isLoadingResults = false;
                    return observableOf([]);
                })
            ).subscribe(data => this.data = data);
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

    addContactsToCorrespondentsGroup(groupId: number) {
        const objTosend = this.selection.selected.map((contactId: any) => ({
            id: contactId,
            type: 'contact'
        }));
        this.http.post('../rest/contactsGroups/' + groupId + '/correspondents', { correspondents: objTosend }).pipe(
            tap(() => {
                this.selection.clear();
                this.notify.success('Contact(s) associé(s)');
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    private _filter(value: string): string[] {
        const filterValue = this.latinisePipe.transform(value.toLowerCase());
        return this.correspondentsGroups.filter((option: any) => this.latinisePipe.transform(this.translate.instant(option['label']).toLowerCase()).includes(filterValue));
    }

    processPostData(data: any) {
        data.contacts.forEach((element: any) => {
            const tmpFormatedAddress = [];
            tmpFormatedAddress.push(element.addressNumber);
            tmpFormatedAddress.push(element.addressStreet);
            tmpFormatedAddress.push(element.addressPostcode);
            tmpFormatedAddress.push(element.addressTown);
            tmpFormatedAddress.push(element.addressCountry);
            element.formatedAddress = tmpFormatedAddress.filter(address => !this.isEmptyValue(address)).join(' ');
            // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
            element.isPrivate = false;
            element.customFields = !this.functionsService.empty(element.customFields) ? this.formatCustomField(element.customFields) : [];
            this.http.get('../rest/parameters/contactsConfidentiality').pipe(
                tap((_data: any) => {
                    element.isPrivate = false;
                    if (_data.parameter) {
                        const param = JSON.parse(_data.parameter.param_value_string);
                        const basicCustomId = param.basic.customId;
                        const advancedCustomId = param.advanced.customId;
                        const allowedBasicField = param.basic.hiddenFields.replace(/\s/g, '').split(',');
                        const advancedBasicField = param.advanced.hiddenFields.replace(/\s/g, '').split(',');
                        const hiddenFields = [];
                        element.customFields.forEach((customField: any) => {
                            if ((customField.id == basicCustomId || customField.id == advancedCustomId) && customField.value == 'Oui') {
                                if (customField.id == basicCustomId) {
                                    allowedBasicField.forEach((_element: any) => {
                                        hiddenFields.push(_element);
                                    });
                                }
                                if (customField.id == advancedCustomId) {
                                    advancedBasicField.forEach((_element: any) => {
                                        hiddenFields.push(_element);
                                    });
                                }
                                if (hiddenFields.includes('address')) {
                                    element.isPrivate = true;
                                }
                            }
                        })
                    }
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
            // END EDISSYUM - NCH01
        });

        if (!this.functions.empty(data.contacts[0]) && !this.functions.empty(data.contacts[0].filling)) {
            this.displayedColumnsContact = ['select', 'filling', 'firstname', 'lastname', 'company', 'formatedAddress', 'actions'];
        } else {
            this.displayedColumnsContact = ['select', 'firstname', 'lastname', 'company', 'formatedAddress', 'actions'];
        }
        return data;
    }

    // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    getCustomFields() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/contactsCustomFields').pipe(
                tap((data: any) => {
                    this.customFields = data.customFields.map((custom: any) => ({
                        id: custom.id,
                        label: custom.label
                    }));
                    resolve(true);
                })
            ).subscribe();
        });
    }

    formatCustomField(data: any) {
        const arrCustomFields: any[] = [];

        Object.keys(data).forEach(element => {
            arrCustomFields.push({
                id: this.customFields.filter(custom => custom.id == element).length > 0 ? this.customFields.filter(custom => custom.id == element)[0].id : undefined,
                label: this.customFields.filter(custom => custom.id == element).length > 0 ? this.customFields.filter(custom => custom.id == element)[0].label : element,
                value: data[element]
            });
        });

        return arrCustomFields;
    }
    // END EDISSYUM - NCH01

    deleteContact(contact: any) {

        if (contact.isUsed) {
            this.dialogRef = this.dialog.open(ContactsListAdministrationRedirectModalComponent, { panelClass: 'maarch-modal', autoFocus: false });
            this.dialogRef.afterClosed().subscribe((result: any) => {
                if (typeof result != 'undefined' && result != '') {
                    let queryparams = '';
                    if (result.processMode == 'reaffect') {
                        queryparams = '?redirect=' + result.contactId;
                    }
                    this.http.request('DELETE', `../rest/contacts/${contact.id}${queryparams}`)
                        .subscribe(() => {
                            this.refreshDao();
                            this.notify.success(this.translate.instant('lang.contactDeleted'));
                        }, (err) => {
                            this.notify.error(err.error.errors);
                        });
                }
                this.dialogRef = null;
            });
        } else {
            const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });
            dialogRef.afterClosed().pipe(
                filter((data: string) => data === 'ok'),
                exhaustMap(() => this.http.delete(`../rest/contacts/${contact.id}`)),
                tap((data: any) => {
                    this.refreshDao();
                    this.notify.success(this.translate.instant('lang.contactDeleted'));
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    toggleContact(contact: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.suspend'), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.put(`../rest/contacts/${contact.id}/activation`, { enabled: !contact.enabled })),
            tap((data: any) => {
                this.refreshDao();
                if (!contact.enabled === true) {
                    this.notify.success(this.translate.instant('lang.contactEnabled'));
                } else {
                    this.notify.success(this.translate.instant('lang.contactDisabled'));
                }
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    openContactExport() {
        this.dialog.open(ContactExportComponent, { panelClass: 'maarch-modal', width: '800px', autoFocus: false });
    }

    openContactImportModal() {
        const dialogRef = this.dialog.open(ContactImportComponent, {
            disableClose: true,
            width: '99vw',
            maxWidth: '99vw',
            panelClass: 'maarch-full-height-modal'
        });

        dialogRef.afterClosed().pipe(
            filter((data: any) => data === 'success'),
            tap(() => {
                this.refreshDao();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    refreshDao() {
        this.selection.clear();
        this.filtersChange.emit();
    }

    initAutocompleteContacts() {
        this.searchContact = new FormControl(this.adminService.getFilter('field'));
        this.searchContact.valueChanges
            .pipe(
                tap((value) => {
                    this.adminService.setFilter('field', value);
                    this.adminService.saveFilter(this.adminService.getFilter());

                    if (value.length === 0) {
                        this.search = '';
                        this.paginator.pageIndex = 0;
                        this.refreshDao();
                    }
                }),
                debounceTime(300),
                filter(value => value.length > 2),
                distinctUntilChanged(),
                tap((data) => {
                    this.search = data;
                    this.paginator.pageIndex = 0;
                    this.refreshDao();
                }),
            ).subscribe();
    }

    isEmptyValue(value: string) {

        if (value === null) {
            return true;

        } else if (Array.isArray(value)) {
            if (value.length > 0) {
                return false;
            } else {
                return true;
            }
        } else if (String(value) !== '') {
            return false;
        } else {
            return true;
        }
    }

    selectContact(contactId: any) {
        this.selection.toggle(contactId);
    }

    isAllSelected() {
        const numSelected = this.selection.selected.length;
        const numRows = this.allContacts.length;
        return numSelected === numRows;
    }

    selectAllContacts() {
        this.isAllSelected() ? this.selection.clear() : this.allContacts.forEach(contactId => this.selection.select(contactId));
    }

    openContactsGroupModal() {
        const dialogRef = this.dialog.open(ContactsGroupFormModalComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '99%',
            height: '99%',
            data: {
                contactIds: this.selection.selected,
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

    open({ x, y }: MouseEvent, element: any) {
        if (!this.selection.isSelected(element.id)) {
            this.selection.clear();
            this.selection.select(element.id);
        }
        // Adjust the menu anchor position
        this.contextMenuPosition.x = x + 'px';
        this.contextMenuPosition.y = y + 'px';

        // Opens the menu
        this.contextMenus.toArray()[this.contextMenus.toArray().map((item: any) => item._element.nativeElement.id).indexOf('menuButtonContext')].openMenu();

        // prevents default
        return false;
    }
}

export interface ContactList {
    contacts: any[];
    count: number;
}
export class ContactListHttpDao {

    constructor(private http: HttpClient) { }

    getRepoIssues(sort: string, order: string, page: number, href: string, search: string): Observable<ContactList> {

        const offset = page * 10;
        const requestUrl = `${href}?limit=10&offset=${offset}&order=${order}&orderBy=${sort}&search=${search}`;

        return this.http.get<ContactList>(requestUrl);
    }
}
@Component({
    templateUrl: 'contacts-list-administration-redirect-modal.component.html',
    styleUrls: [],
})
export class ContactsListAdministrationRedirectModalComponent {

    modalTitle: string = this.translate.instant('lang.confirmAction');
    redirectContact: number;
    processMode: string = 'delete';

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<ContactsListAdministrationRedirectModalComponent>,
        private notify: NotificationService) {
    }

    ngOnInit(): void {

    }

    setRedirectUser(contact: any) {
        this.redirectContact = contact.id;
    }
}
