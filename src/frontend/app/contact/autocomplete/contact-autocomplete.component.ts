import { Component, OnInit, Input, ViewChild, ElementRef, Output, EventEmitter } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { AppService } from '@service/app.service';
import { SortPipe } from '../../../plugins/sorting.pipe';
import { FormControl } from '@angular/forms';
import { Observable, of } from 'rxjs';
import { debounceTime, filter, tap, switchMap, catchError, finalize, map } from 'rxjs/operators';
import { LatinisePipe } from 'ngx-pipes';
import { PrivilegeService } from '@service/privileges.service';
import { ContactModalComponent } from '../../administration/contact/modal/contact-modal.component';
import { ContactService } from '@service/contact.service';
import { FunctionsService } from '@service/functions.service';

interface DisplayContactList {
    'contactGroup': any; // EDISSYUM - NCH01 Rajout de la possibilité de link un groupement de correspondants dans un autre
    'contact': any;
    'user': any;
    'entity': any;
}

@Component({
    selector: 'app-contact-autocomplete',
    templateUrl: 'contact-autocomplete.component.html',
    styleUrls: [
        'contact-autocomplete.component.scss',
        '../../indexation/indexing-form/indexing-form.component.scss'
    ],
    providers: [SortPipe, ContactService]
})

export class ContactAutocompleteComponent implements OnInit {

    loading: boolean = false;
    loadingValues: boolean = true;

    key: string = 'id';

    canAdd: boolean = false;
    canUpdate: boolean = false;

    noResultFound: boolean = null;

    listInfo: string;
    myControl = new FormControl();
    filteredOptions: Observable<string[]>;
    options: any;
    valuesToDisplay: DisplayContactList = {
        contactGroup: {}, // EDISSYUM - NCH01 Rajout de la possibilité de link un groupement de correspondants dans un autre
        contact : {},
        user: {},
        entity: {}
    };
    dialogRef: MatDialogRef<any>;
    newIds: number[] = [];
    customFields: any[] = [];

    basicCustomId: any; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    advancedCustomId: any; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    basicConfidentiality: boolean = false; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    advancedConfidentiality: boolean = false; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    basicAllowedEntities: any = []; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    advancedAllowedEntities: any = []; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    advancedHiddenFields: any = []; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    basicHiddenFields: any = []; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts

    /**
     * FormControl used when autocomplete is used in form and must be catched in a form control.
     */
    @Input('control') controlAutocomplete: FormControl = new FormControl();

    @Input() id: string = 'contact-autocomplete';
    @Input() exclusion: string = '';

    @Input() singleMode: boolean = false;
    @Input() inputMode: boolean = false;

    @Output() retrieveDocumentEvent = new EventEmitter<string>();
    @Output() afterSelected = new EventEmitter<any>();
    @Output() removeContactEvent = new EventEmitter<any>();
    @Output() afterContactSelected = new EventEmitter<any>();

    @ViewChild('autoCompleteInput', { static: true }) autoCompleteInput: ElementRef;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialog: MatDialog,
        private headerService: HeaderService,
        public appService: AppService,
        private latinisePipe: LatinisePipe,
        private privilegeService: PrivilegeService,
        private contactService: ContactService,
        public functions: FunctionsService
    ) {

    }

    // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    isConfidential(element: any, field) {
        let userEntityAllowed = true;
        let requiredPrivilege;
        let hiddenFields = [];
        this.basicHiddenFields.forEach((hidden_field: any) => {
            if (hidden_field === field) {
                hiddenFields = this.basicHiddenFields;
                requiredPrivilege = this.privilegeService.hasCurrentUserPrivilege('view_confidential_contact');
                if (this.basicAllowedEntities && this.basicAllowedEntities.length > 0) {
                    userEntityAllowed = false;
                    this.headerService.user.entities.forEach((element: any) => {
                        if (this.basicAllowedEntities.includes(element.entity_id)) {
                            userEntityAllowed = true;
                        }
                    });
                }
            }
        });

        this.advancedHiddenFields.forEach((hidden_field: any) => {
            if (hidden_field === field) {
                hiddenFields = this.advancedHiddenFields;
                requiredPrivilege = this.privilegeService.hasCurrentUserPrivilege('view_confidential_contact_advanced');
                if (this.advancedAllowedEntities && this.advancedAllowedEntities.length > 0) {
                    userEntityAllowed = false;
                    this.headerService.user.entities.forEach((element: any) => {
                        if (this.advancedAllowedEntities.includes(element.entity_id)) {
                            userEntityAllowed = true;
                        }
                    });
                }
            }
        });
        return element.isPrivate && (!requiredPrivilege || !userEntityAllowed) && hiddenFields.includes(field);

    }

    isNotConfidential(element: any, field) {
        let userEntityAllowed = true;
        let requiredPrivilege;
        let hiddenFields = [];

        this.basicHiddenFields.forEach((element: any) => {
            if (element === field) {
                hiddenFields = this.basicHiddenFields;
                requiredPrivilege = this.privilegeService.hasCurrentUserPrivilege('view_confidential_contact');
                if (this.basicAllowedEntities && this.basicAllowedEntities.length > 0) {
                    userEntityAllowed = false;
                    this.headerService.user.entities.forEach((element: any) => {
                        if (this.basicAllowedEntities.includes(element.entity_id)) {
                            userEntityAllowed = true;
                        }
                    });
                }
            }
        });

        this.advancedHiddenFields.forEach((element: any) => {
            if (element === field) {
                hiddenFields = this.advancedHiddenFields;
                requiredPrivilege = this.privilegeService.hasCurrentUserPrivilege('view_confidential_contact_advanced');
                if (this.advancedAllowedEntities && this.advancedAllowedEntities.length > 0) {
                    userEntityAllowed = false;
                    this.headerService.user.entities.forEach((element: any) => {
                        if (this.advancedAllowedEntities.includes(element.entity_id)) {
                            userEntityAllowed = true;
                        }
                    });
                }
            }
        });
        return !element.isPrivate || requiredPrivilege && userEntityAllowed || !hiddenFields.includes(field);
    }

    isNotCustomConfidential(custom_id: any) {
        return !(parseInt(this.basicCustomId) === parseInt(custom_id) || parseInt(this.advancedCustomId) === parseInt(custom_id));
    }
    // END EDISSYUM - NCH01

    ngOnInit() {
        this.controlAutocomplete.setValue(this.controlAutocomplete.value === null || this.controlAutocomplete.value === '' ? [] : this.controlAutocomplete.value);
        this.canAdd = this.privilegeService.hasCurrentUserPrivilege('create_contacts');
        this.canUpdate = this.privilegeService.hasCurrentUserPrivilege('update_contacts');
        this.getCustomFields();
        this.initFormValue();
        this.initAutocompleteRoute();
    }

    initAutocompleteRoute() {
        this.listInfo = this.translate.instant('lang.autocompleteInfo');
        this.options = [];
        this.myControl.valueChanges
            .pipe(
                tap(() => {
                    this.noResultFound = null;
                    this.options = [];
                    this.listInfo = this.translate.instant('lang.autocompleteInfo');
                }),
                debounceTime(300),
                filter(value => value.length > 2),
                // distinctUntilChanged(),
                tap(() => this.loading = true),
                switchMap((data: any) => this.getDatas(data)),
                map((data: any) => {
                    data = data.filter((contact: any) => !this.singleMode || (contact.type !== 'entity' && contact.type !== 'contactGroup' && this.singleMode));
                    data = data.map((contact: any) => ({
                        ...contact,
                        civility: this.contactService.formatCivilityObject(contact.civility),
                        fillingRate: this.contactService.formatFillingObject(contact.fillingRate),
                        customFields: contact.customFields !== undefined ? this.formatCustomField(contact.customFields) : [],
                    }));
                    return data;
                }),
                tap((data: any) => {
                    if (data.length === 0) {
                        this.noResultFound = true;
                        this.listInfo = this.translate.instant('lang.noAvailableValue');
                    } else {
                        this.noResultFound = false;
                        this.listInfo = '';
                    }
                    this.options = data;
                    // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
                    this.http.get('../rest/parameters/contactsConfidentiality').pipe(
                        tap((data: any) => {
                            this.options.forEach((element: any) => {
                                element.isPrivate = false;
                                if (data.parameter) {
                                    let param = JSON.parse(data.parameter.param_value_string);
                                    this.basicCustomId = param.basic.customId;
                                    this.advancedCustomId = param.advanced.customId;
                                    const basicAllowedField = param.basic.hiddenFields.replace(/\s/g, "").split(',');
                                    const advancedAllowedField = param.advanced.hiddenFields.replace(/\s/g, "").split(',');
                                    const basicAllowedEntities = param.basic.entitiesAllowed.replace(/\s/g, "").split(',');
                                    const advancedAllowedEntities = param.advanced.entitiesAllowed.replace(/\s/g, "").split(',');
                                    element.customFields.forEach((customField: any) => {
                                        if ((customField.id == this.basicCustomId || customField.id == this.advancedCustomId) && customField.value == 'Oui') {
                                            element.isPrivate = true;
                                            if (customField.id == this.basicCustomId) {
                                                this.basicConfidentiality = true;
                                                basicAllowedField.forEach((element: any) => {
                                                    this.basicHiddenFields.push(element);
                                                });
                                                basicAllowedEntities.forEach((element: any) => {
                                                    if (element && element !== '*') {
                                                        this.basicAllowedEntities.push(element);
                                                    }
                                                });
                                            }
                                            if (customField.id == this.advancedCustomId) {
                                                this.advancedConfidentiality = true;
                                                advancedAllowedField.forEach((element: any) => {
                                                    this.advancedHiddenFields.push(element);
                                                });
                                                advancedAllowedEntities.forEach((element: any) => {
                                                    if (element && element !== '*') {
                                                        this.advancedAllowedEntities.push(element);
                                                    }
                                                });
                                            }
                                        }
                                    })
                                }
                            })
                        }),
                        catchError((err: any) => {
                            this.notify.handleSoftErrors(err);
                            return of(false);
                        })
                    ).subscribe();
                    // END EDISSYUM - NCH01
                    this.filteredOptions = of(this.options);
                    this.loading = false;
                })
            ).subscribe();
    }

    getCustomFields() {
        this.http.get('../rest/contactsCustomFields').pipe(
            tap((data: any) => {
                this.customFields = data.customFields.map((custom: any) => ({
                    id: custom.id,
                    label: custom.label
                }));
            })
        ).subscribe();
    }

    formatCustomField(data: any) {
        const arrCustomFields: any[] = [];
        // EDISSYUM - NCH01 Rajout de la confidentialité des contacts Ajout du if (data)
        if (data) {
            Object.keys(data).forEach(element => {
                if (this.customFields.filter(custom => custom.id === element).length > 0) { // EDISSYUM - NCH01 Rajout de la confidentialité des contacts Ajout du if
                    arrCustomFields.push({
                        id: this.customFields.filter(custom => custom.id === element).length > 0 ? this.customFields.filter(custom => custom.id === element)[0].id : undefined, // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
                        label: this.customFields.filter(custom => custom.id == element)[0].label,
                        value: data[element]
                    });
                }
            });
        }

        return arrCustomFields;
    }

    getDatas(data: string) {
        return this.http.get('../rest/autocomplete/correspondents' + this.exclusion, { params: { 'search': data } });
    }

    selectOpt(ev: any) {
        this.setFormValue(ev.option.value);
        this.afterSelected.emit(ev.option.value);
        this.myControl.setValue('');
        this.afterContactSelected.emit(ev.option.value);
    }

    initFormValue() {
        this.controlAutocomplete.value.forEach((contact: any) => {
            this.valuesToDisplay[contact.type][contact.id] = {
                type: '',
                firstname: '',
                lastname: this.translate.instant('lang.undefined'),
                company: '',
                fillingRate: {
                    color: ''
                }
            };

            if (contact.type === 'contact') {
                this.http.get('../rest/contacts/' + contact.id).pipe(
                    tap((data: any) => {
                        this.valuesToDisplay['contact'][data.id] = {
                            type: 'contact',
                            firstname: data.firstname,
                            lastname: data.lastname,
                            company: data.company,
                            fillingRate: !this.functions.empty(data.fillingRate) ? {
                                color: this.contactService.getFillingColor(data.fillingRate.thresholdLevel)
                            } : '',
                            sector: data.sector
                        };
                    }),
                    finalize(() => this.loadingValues = false),
                    catchError((err: any) => {
                        this.notify.error(err.error.errors);
                        return of(false);
                    })
                ).subscribe();
            } else if (contact.type === 'user') {
                this.http.get('../rest/users/' + contact.id).pipe(
                    tap((data: any) => {
                        this.valuesToDisplay['user'][data.id] = {
                            type: 'user',
                            firstname: data.firstname,
                            lastname: data.lastname,
                            fillingRate: {
                                color: ''
                            }
                        };
                    }),
                    finalize(() => this.loadingValues = false),
                    catchError((err: any) => {
                        this.notify.error(err.error.errors);
                        return of(false);
                    })
                ).subscribe();
            } else if (contact.type === 'entity') {
                this.http.get('../rest/entities/' + contact.id).pipe(
                    tap((data: any) => {
                        this.valuesToDisplay['entity'][data.id] = {
                            type: 'entity',
                            lastname: data.entity_label,
                            fillingRate: {
                                color: ''
                            }
                        };
                    }),
                    finalize(() => this.loadingValues = false),
                    catchError((err: any) => {
                        this.notify.error(err.error.errors);
                        return of(false);
                    })
                ).subscribe();
            }
        });
    }

    setFormValue(item: any) {
        if (item.type === 'contactGroup') {
            this.http.get('../rest/contactsGroups/' + item.id + '/correspondents?limit=none').pipe(
                map((data: any) => {
                    const contacts = data.correspondents.map((correspondent: any) => ({
                        id: correspondent.id,
                        type: correspondent.type,
                        lastname: correspondent.name,
                        sector: correspondent.sector,
                        fillingRate: !this.functions.empty(correspondent.thresholdLevel) ? {
                            color: this.contactService.getFillingColor(correspondent.thresholdLevel)
                        } : ''
                    }));
                    return contacts;
                }),
                tap((contacts: any) => {
                    contacts.forEach((contact: any) => {
                        // EDISSYUM - NCH01 Rajout de la possibilité de link un groupement de correspondants dans un autre
                        if (contact.type === 'contactGroup') {
                            this.http.get(`../rest/contactsGroups/${contact.id}/correspondents`).pipe(
                                tap((data: any) => {
                                    this.setContactRecursively(data);
                                }),
                                catchError((err) => {
                                    this.notify.handleSoftErrors(err);
                                    return of(false);
                                })
                            ).subscribe();
                        } else {
                            this.setContact(contact);
                        }
                        // END EDISSYM - NCH01
                        // this.setContact(contact); // EDISSYUM - NCH01 Rajout de la possibilité de link un groupement de correspondants dans un autre | Commenter la ligne
                    });
                }),
                finalize(() => this.loadingValues = false),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.setContact(item);
        }
    }

    // EDISSYUM - NCH01 Rajout de la possibilité de link un groupement de correspondants dans un autre
    setContactRecursively(contacts: any) {
        contacts.correspondents.forEach((contact: any) => {
            if (contact.type === 'contactGroup') {
                this.http.get(`../rest/contactsGroups/${contact.id}/correspondents`).pipe(
                    tap((data: any) => {
                        this.setContactRecursively(data);
                    }),
                    catchError((err) => {
                        this.notify.handleSoftErrors(err);
                        return of(false);
                    })
                ).subscribe();
            } else {
                this.setContact({
                    id: contact.id,
                    type: contact.type,
                    lastname: contact.name,
                    sector: contact.sector,
                    fillingRate: !this.functions.empty(contact.thresholdLevel) ? {
                        color: this.contactService.getFillingColor(contact.thresholdLevel)
                    } : ''
                });
            }
        });
    }
    // END EDISSYUM - NCH01

    setContact(contact: any) {
        if (this.controlAutocomplete.value.filter((contactItem: any) => contactItem.id === contact.id && contactItem.type === contact.type).length === 0) {
            let arrvalue = [];
            if (this.controlAutocomplete.value !== null) {
                arrvalue = this.controlAutocomplete.value;
            }
            this.valuesToDisplay[contact['type']][contact['id']] = contact;
            arrvalue.push(
                {
                    type: contact['type'],
                    id: contact['id'],
                    label: this.getFormatedContact(contact['type'], contact['id']),
                    sector: contact['sector'],
                    isPrivate: contact.isPrivate, // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
                });
            this.controlAutocomplete.setValue(arrvalue);
            this.loadingValues = false;
        }
    }

    resetAutocomplete() {
        this.options = [];
        this.listInfo = this.translate.instant('lang.autocompleteInfo');
        this.myControl.setValue('');
    }


    unsetValue() {
        this.controlAutocomplete.setValue('');
        this.myControl.setValue('');
        this.myControl.enable();
    }

    removeItem(index: number) {
        this.removeContactEvent.emit(this.controlAutocomplete.value[index].id);
        if (this.newIds.indexOf(this.controlAutocomplete.value[index]) === -1) {
            const arrValue = this.controlAutocomplete.value;
            this.controlAutocomplete.value.splice(index, 1);
            this.controlAutocomplete.setValue(arrValue);
        } else {
            this.http.delete('../rest/tags/' + this.controlAutocomplete.value[index]).pipe(
                tap((data: any) => {
                    const arrValue = this.controlAutocomplete.value;
                    this.controlAutocomplete.value.splice(index, 1);
                    this.controlAutocomplete.setValue(arrValue);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    openContact(contact: any = null) {
        this.retrieveDocumentEvent.emit();
        const dialogRef = this.dialog.open(
            ContactModalComponent,
            {
                maxWidth: '100vw',
                width: contact === null ? '99vw' : 'auto',
                panelClass: contact === null ? 'maarch-full-height-modal' : 'maarch-modal',
                disableClose: true,
                data: {
                    isPrivate: contact !== null ? contact.isPrivate : null, // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
                    editMode: this.canUpdate,
                    contactId: contact !== null ? contact.id : null,
                    contactType: contact !== null ? contact.type : null }
            }
        );

        dialogRef.afterClosed().pipe(
            filter((data: number) => data !== undefined),
            tap((contactId: number) => {
                const newContact = {
                    type: 'contact',
                    id: contactId
                };
                this.setFormValue(newContact);
                this.initFormValue();
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    empty(value: any) {
        if (value !== null && value !== '' && value !== undefined) {
            return false;
        } else {
            return true;
        }
    }

    resetAll() {
        this.controlAutocomplete.setValue([]);
        this.removeContactEvent.emit(false);
        this.valuesToDisplay = {
            contactGroup: {}, // EDISSYUM - NCH01 Rajout de la possibilité de link un groupement de correspondants dans un autre
            contact : {},
            user: {},
            entity: {}
        };
    }

    getFormatedContact(type: string, id: number) {
        return this.contactService.formatContact(this.valuesToDisplay[type][id]);
    }

    getInputValue() {
        return this.myControl.value;
    }

    setInputValue(value: string) {
        this.myControl.setValue(value);
    }

    resetInputValue() {
        this.myControl.setValue('');
    }

    private _filter(value: string): string[] {
        if (typeof value === 'string') {
            const filterValue = this.latinisePipe.transform(value.toLowerCase());
            return this.options.filter((option: any) => this.latinisePipe.transform(option[this.key].toLowerCase()).includes(filterValue));
        } else {
            return this.options;
        }
    }
}
