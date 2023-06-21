import { Component, OnInit, Output, EventEmitter, Input } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { ContactService } from '@service/contact.service';
import { tap, catchError, finalize } from 'rxjs/operators';
import { TranslateService } from '@ngx-translate/core';
import { FunctionsService } from '@service/functions.service';
import { of } from 'rxjs';
import { HeaderService } from "@service/header.service"; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
import { PrivilegeService } from "@service/privileges.service"; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts

@Component({
    selector: 'app-contact-detail',
    templateUrl: './contact-detail.component.html',
    styleUrls: ['./contact-detail.component.scss'],
    providers: [ContactService]
})
export class ContactDetailComponent implements OnInit {

    /**
     * [Id of contact to load a specific resource]
     * DO NOT USE with @resId
     * ex : {id: 1, type: 'contact'}
     */
    @Input() contact: any = {};

    @Input() selectable: boolean = false;

    @Output() afterSelectedEvent = new EventEmitter<any>();
    @Output() afterDeselectedEvent = new EventEmitter<any>();


    loading: boolean = true;

    contactClone: any = {};
    customFields: any[] = [];

    basicConfidentiality: boolean = false; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    advancedConfidentiality: boolean = false; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    basicCustomId: any; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    advancedCustomId: any; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private contactService: ContactService,
        public functionsService: FunctionsService,
        public privilegeService: PrivilegeService, // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
        private headerService: HeaderService // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    ) { }

    // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    isConfidential(field: string) {
        let userEntityAllowed = true
        let requiredPrivilege;
        let hiddenFields = [];
        if (this.contact.basicHiddenFields) {
            this.contact.basicHiddenFields.forEach((element: any) => {
                if (element === field) {
                    hiddenFields = this.contact.basicHiddenFields;
                    requiredPrivilege = this.privilegeService.hasCurrentUserPrivilege('view_confidential_contact');
                    if (this.contact.basicAllowedEntities && this.contact.basicAllowedEntities.length > 0) {
                        userEntityAllowed = false;
                        this.headerService.user.entities.forEach((element: any) => {
                            if (this.contact.basicAllowedEntities.includes(element.entity_id)) {
                                userEntityAllowed = true;
                            }
                        });
                    }
                }
            });
        }

        if (this.contact.advancedHiddenFields) {
            this.contact.advancedHiddenFields.forEach((element: any) => {
                if (element === field) {
                    hiddenFields = this.contact.advancedHiddenFields;
                    requiredPrivilege = this.privilegeService.hasCurrentUserPrivilege('view_confidential_contact_advanced');
                    if (this.contact.advancedAllowedEntities && this.contact.advancedAllowedEntities.length > 0) {
                        userEntityAllowed = false;
                        this.headerService.user.entities.forEach((element: any) => {
                            if (this.contact.advancedAllowedEntities.includes(element.entity_id)) {
                                userEntityAllowed = true;
                            }
                        });
                    }
                }
            });
        }
        return this.contact.isPrivate && (!requiredPrivilege || !userEntityAllowed) && hiddenFields.includes(field);
    }

    isNotConfidential(field: string) {
        let userEntityAllowed = true;
        let requiredPrivilege;
        let hiddenFields = [];

        if (this.contact.basicHiddenFields) {
            this.contact.basicHiddenFields.forEach((element: any) => {
                if (element === field) {
                    hiddenFields = this.contact.basicHiddenFields;
                    requiredPrivilege = this.privilegeService.hasCurrentUserPrivilege('view_confidential_contact');
                    if (this.contact.basicAllowedEntities && this.contact.basicAllowedEntities.length > 0) {
                        userEntityAllowed = false;
                        this.headerService.user.entities.forEach((element: any) => {
                            if (this.contact.basicAllowedEntities.includes(element.entity_id)) {
                                userEntityAllowed = true;
                            }
                        });
                    }
                }
            });
        }

        if (this.contact.advancedHiddenFields) {
            this.contact.advancedHiddenFields.forEach((element: any) => {
                if (element === field) {
                    hiddenFields = this.contact.advancedHiddenFields;
                    requiredPrivilege = this.privilegeService.hasCurrentUserPrivilege('view_confidential_contact_advanced');
                    if (this.contact.advancedAllowedEntities && this.contact.advancedAllowedEntities.length > 0) {
                        userEntityAllowed = false;
                        this.headerService.user.entities.forEach((element: any) => {
                            if (this.contact.advancedAllowedEntities.includes(element.entity_id)) {
                                userEntityAllowed = true;
                            }
                        });
                    }
                }
            });
        }

        return !this.contact.isPrivate || requiredPrivilege && userEntityAllowed || !hiddenFields.includes(field);
    }

    // END EDISSYUM - NCH01

    async ngOnInit(): Promise<void> {

        await this.getCustomFields();

        if (Object.keys(this.contact).length === 2) {
            this.loadContact(this.contact.id, this.contact.type);
        } else if (Object.keys(this.contact).length > 2) {
            this.contactClone = JSON.parse(JSON.stringify(this.contact));
            // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
            this.http.get('../rest/parameters/contactsConfidentiality').pipe(
                tap((data: any) => {
                    if (data.parameter) {
                        let param = JSON.parse(data.parameter.param_value_string);
                        this.basicCustomId = param.basic.customId;
                        this.advancedCustomId = param.advanced.customId;
                        const basicAllowedField = param.basic.hiddenFields.replace(/\s/g, "").split(',');
                        const advancedAllowedField = param.advanced.hiddenFields.replace(/\s/g, "").split(',');
                        const basicAllowedEntities = param.basic.entitiesAllowed.replace(/\s/g, "").split(',');
                        const advancedAllowedEntities = param.advanced.entitiesAllowed.replace(/\s/g, "").split(',');
                        this.contact.basicHiddenFields = [];
                        this.contact.advancedHiddenFields = [];
                        this.contact.basicAllowedEntities = [];
                        this.contact.advancedAllowedEntities = [];
                        this.contact.customFields.forEach((customField: any) => {
                            if ((customField.id == this.basicCustomId || customField.id == this.advancedCustomId) && customField.value == 'Oui') {
                                this.contact.isPrivate = true;
                                if (customField.id == this.basicCustomId) {
                                    this.basicConfidentiality = true;
                                    basicAllowedField.forEach((element: any) => {
                                        this.contact.basicHiddenFields.push(element);
                                    });
                                    basicAllowedEntities.forEach((element: any) => {
                                        if (element && element !== '*') {
                                            this.contact.basicAllowedEntities.push(element);
                                        }
                                    });
                                }
                                if (customField.id == this.advancedCustomId) {
                                    this.advancedConfidentiality = true;
                                    advancedAllowedField.forEach((element: any) => {
                                        this.contact.advancedHiddenFields.push(element);
                                    });
                                    advancedAllowedEntities.forEach((element: any) => {
                                        if (element && element !== '*') {
                                            this.contact.advancedAllowedEntities.push(element);
                                        }
                                    });
                                }
                            }
                        });
                    }
                }),
                finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
            // END EDISSYUM - NCH01
        }
    }

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

    loadContact(contactId: number, type: string) {

        if (type === 'contact') {
            const queryParam: string = this.selectable ? '?resourcesCount=true' : '';
            this.http.get('../rest/contacts/' + contactId + queryParam).pipe(
                tap((contact: any) => {
                    this.contact = {
                        ...contact,
                        civility: this.contactService.formatCivilityObject(contact.civility),
                        isPrivate: this.contact.isPrivate, // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
                        fillingRate: this.contactService.formatFillingObject(contact.fillingRate),
                        customFields: !this.functionsService.empty(contact.customFields) ? this.formatCustomField(contact.customFields) : [],
                        type: 'contact'
                    };
                    // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
                    this.http.get('../rest/parameters/contactsConfidentiality').pipe(
                        tap((data: any) => {
                            this.contact.isPrivate = false;
                            if (data.parameter) {
                                let param = JSON.parse(data.parameter.param_value_string);
                                this.basicCustomId = param.basic.customId;
                                this.advancedCustomId = param.advanced.customId;
                                const basicAllowedField = param.basic.hiddenFields.replace(/\s/g, "").split(',');
                                const advancedAllowedField = param.advanced.hiddenFields.replace(/\s/g, "").split(',');
                                const basicAllowedEntities = param.basic.entitiesAllowed.replace(/\s/g, "").split(',');
                                const advancedAllowedEntities = param.advanced.entitiesAllowed.replace(/\s/g, "").split(',');
                                this.contact.basicHiddenFields = [];
                                this.contact.advancedHiddenFields = [];
                                this.contact.basicAllowedEntities = [];
                                this.contact.advancedAllowedEntities = [];
                                this.contact.customFields.forEach((customField: any) => {
                                    if ((customField.id == this.basicCustomId || customField.id == this.advancedCustomId) && customField.value == 'Oui') {
                                        this.contact.isPrivate = true;
                                        if (customField.id == this.basicCustomId) {
                                            this.basicConfidentiality = true;
                                            basicAllowedField.forEach((element: any) => {
                                                this.contact.basicHiddenFields.push(element);
                                            });
                                            basicAllowedEntities.forEach((element: any) => {
                                                if (element && element !== '*') {
                                                    this.contact.basicAllowedEntities.push(element);
                                                }
                                            });
                                        }
                                        if (customField.id == this.advancedCustomId) {
                                            this.advancedConfidentiality = true;
                                            advancedAllowedField.forEach((element: any) => {
                                                this.contact.advancedHiddenFields.push(element);
                                            });
                                            advancedAllowedEntities.forEach((element: any) => {
                                                if (element && element !== '*') {
                                                    this.contact.advancedAllowedEntities.push(element);
                                                }
                                            });
                                        }
                                    }
                                });
                            }
                        }),
                        catchError((err: any) => {
                            this.notify.handleSoftErrors(err);
                            return of(false);
                        })
                    ).subscribe();
                    // END EDISSYUM - NCH01
                    this.contactClone = JSON.parse(JSON.stringify(this.contact));
                }),
                finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else if (type === 'user') {
            this.http.get('../rest/users/' + contactId).pipe(
                tap((data: any) => {
                    this.contact = {
                        type: 'user',
                        civility: this.contactService.formatCivilityObject(null),
                        fillingRate: this.contactService.formatFillingObject(null),
                        customFields: [],
                        firstname: data.firstname,
                        lastname: data.lastname,
                        email: data.mail,
                        department: data.department,
                        phone: data.phone,
                        enabled: data.enabled
                    };
                    this.contactClone = JSON.parse(JSON.stringify(this.contact));
                }),
                finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else if (type === 'entity') {
            this.http.get('../rest/entities/' + contactId).pipe(
                tap((data: any) => {
                    this.contact = {
                        ...data,
                        type: 'entity',
                        civility: this.contactService.formatCivilityObject(null),
                        fillingRate: this.contactService.formatFillingObject(null),
                        customFields: [],
                        lastname: data.short_label,
                        enabled: data.enabled === 'Y'
                    };
                    this.contactClone = JSON.parse(JSON.stringify(this.contact));
                }),
                finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.error(err.error.errors);
                    return of(false);
                })
            ).subscribe();
        }
    }

    formatCustomField(data: any) {
        const arrCustomFields: any[] = [];

        Object.keys(data).forEach(element => {
            arrCustomFields.push({
                id: this.customFields.filter(custom => custom.id == element).length > 0 ? this.customFields.filter(custom => custom.id == element)[0].id : undefined, // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
                label: this.customFields.filter(custom => custom.id == element).length > 0 ? this.customFields.filter(custom => custom.id == element)[0].label : element,
                value: data[element]
            });
        });

        return arrCustomFields;
    }

    goTo(contact: any) {
        window.open(`https://www.google.com/maps/search/${contact.addressNumber}+${contact.addressStreet},+${contact.addressPostcode}+${contact.addressTown},+${contact.addressCountry}`, '_blank');
    }

    emptyOtherInfo(contact: any) {

        if (contact.type === 'contact' && (!this.functionsService.empty(contact.notes) || !this.functionsService.empty(contact.communicationMeans) || !this.functionsService.empty(contact.customFields))) {
            return false;
        } else {
            return true;
        }
    }

    toggleContact(contact: any) {
        contact.selected = !contact.selected;

        if (contact.selected) {
            this.afterSelectedEvent.emit(contact);
        } else {
            this.afterDeselectedEvent.emit(contact);
        }
    }

    getContactInfo() {
        return this.contact;
    }

    resetContact() {
        this.contact = JSON.parse(JSON.stringify(this.contactClone));
    }

    setContactInfo(identifier: string, value: string) {
        if (!this.functionsService.empty(value)) {
            if (identifier === 'customFields') {
                this.contact[identifier].push(value);
            } else {
                this.contact[identifier] = value;
            }
        }
    }

    isNewValue(identifier: any) {
        const isCustomField = typeof identifier === 'object' && identifier !== 'civility';

        if (isCustomField) {
            return this.contactClone['customFields'].filter((custom: any) => custom.label === identifier.value.label).length === 0;
        } else if (identifier === 'civility') {
            return JSON.stringify(this.contact[identifier]) !== JSON.stringify(this.contactClone[identifier]);
        } else {
            return this.contact[identifier] !== this.contactClone[identifier];
        }
    }
}
