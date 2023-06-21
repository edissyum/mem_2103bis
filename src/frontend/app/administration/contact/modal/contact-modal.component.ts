import { Component, Inject, ViewChild, Renderer2, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialog } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { PrivilegeService } from '@service/privileges.service';
import { HeaderService } from '@service/header.service';
import { MatSidenav } from '@angular/material/sidenav';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import {catchError, exhaustMap, filter, tap} from 'rxjs/operators';
import { of } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { FunctionsService } from "@service/functions.service"; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts

declare let $: any;

@Component({
    templateUrl: 'contact-modal.component.html',
    styleUrls: ['contact-modal.component.scss'],
})
export class ContactModalComponent implements OnInit{

    creationMode: boolean = true;
    canUpdate: boolean = false;
    contact: any = null;
    mode: 'update' | 'read' = 'read';
    loadedDocument: boolean = false;
    customFields = []; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    basicConfidentiality: boolean = false; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    advancedConfidentiality: boolean = false; // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    @ViewChild('drawer', { static: true }) drawer: MatSidenav;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public functionsService: FunctionsService, // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
        private privilegeService: PrivilegeService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<ContactModalComponent>,
        public headerService: HeaderService,
        public dialog: MatDialog,
        public notify: NotificationService,
        private renderer: Renderer2) {
    }

    // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    isConfidential(data) {
        let userEntityAllowed = true;
        if (data.allowedGroups && data.allowedGroups.length > 0) {
            userEntityAllowed = false;
            this.headerService.user.entities.forEach((element: any) => {
                if (data.allowedEntities.includes(element.entity_id)) {
                    userEntityAllowed = true;
                }
            });
        }

        let requiredPrivilege;
        if (this.basicConfidentiality) requiredPrivilege = this.privilegeService.hasCurrentUserPrivilege('view_confidential_contact');
        else if (this.advancedConfidentiality) requiredPrivilege = this.privilegeService.hasCurrentUserPrivilege('view_confidential_contact_advanced');

        return this.data.isPrivate && (!requiredPrivilege || !userEntityAllowed);
    }
    // END EDISSYUM - NCH01

    async ngOnInit(): Promise<void> { // EDISSYUM - NCH01 Rajout de la confidentialité des contacts | add ASYNC et Promise <void>
        if (this.data.contactId !== null) {
            this.contact = {
                id: this.data.contactId,
                type: this.data.contactType
            };
            // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
            await this.getCustomFields();
            // EDISSYUM - EME01 FIX si contact associé au courrier est de type user dans la fiche détaillée || ajout du if (this.contact.contactType === 'contact')
            if (this.contact.contactType === 'contact') {
                await this.loadContact(this.data.contactId);
            }
            // END EDISSYUM - EME01
            // END EDISSYUM - NCH01
            this.creationMode = false;
        } else {
            this.creationMode = true;
            this.mode = 'update';
            if (this.mode === 'update') {
                $('.maarch-modal').css({ 'height': '99vh' });
                $('.maarch-modal').css({ 'width': '99vw' });
            }
            if (this.headerService.getLastLoadedFile() !== null) {
                this.drawer.toggle();
                setTimeout(() => {
                    this.loadedDocument = true;
                }, 200);
            }
        }
        this.canUpdate = this.privilegeService.hasCurrentUserPrivilege('update_contacts');
    }

    // EDISSYUM - NCH01 Rajout de la confidentialité des contacts
    loadContact(contactId: number) {
        this.http.get('../rest/contacts/' + contactId).pipe(
            tap((contact: any) => {
                this.data.isPrivate = false;
                const customFields = !this.functionsService.empty(contact.customFields) ? this.formatCustomField(contact.customFields) : []
                this.http.get('../rest/parameters/contactsConfidentiality').pipe(
                    tap((data: any) => {
                        if (data.parameter) {
                            let param = JSON.parse(data.parameter.param_value_string);
                            const basicCustomId = param.basic.customId;
                            const advancedCustomId = param.advanced.customId;
                            const basicAllowedEntities = param.basic.entitiesAllowed.replace(/\s/g, "").split(',');
                            const advancedAllowedEntities = param.advanced.entitiesAllowed.replace(/\s/g, "").split(',');
                            this.data.allowedEntities = [];
                            customFields.forEach((customField: any) => {
                                if ((customField.id == basicCustomId || customField.id == advancedCustomId) && customField.value == 'Oui') {
                                    this.data.isPrivate = true;
                                    if (customField.id == basicCustomId) {
                                        this.basicConfidentiality = true;
                                        basicAllowedEntities.forEach((element: any) => {
                                            if (element && element !== '*') {
                                                this.data.allowedEntities.push(element);
                                            }
                                        });
                                    }
                                    if (customField.id == advancedCustomId) {
                                        this.advancedConfidentiality = true;
                                        advancedAllowedEntities.forEach((element: any) => {
                                            if (element && element !== '*') {
                                                this.data.allowedEntities.push(element);
                                            }
                                        });
                                    }
                                }
                            });
                        }
                    }),
                    catchError(() => {
                        return of(false);
                    })
                ).subscribe();
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
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
    // END EDISSYUM - NCH01

    switchMode() {
        this.mode = this.mode === 'read' ? 'update' : 'read';
        if (this.mode === 'update') {
            $('.maarch-modal').css({ 'height': '99vh' });
            $('.maarch-modal').css({ 'width': '99vw' });
        }

        if (this.headerService.getLastLoadedFile() !== null) {
            this.drawer.toggle();
            setTimeout(() => {
                this.loadedDocument = true;
            }, 200);
        }
    }

    linkContact(contactId: number) {
        const dialogRef = this.dialog.open(ConfirmComponent,
            { panelClass: 'maarch-modal',
                autoFocus: false, disableClose: true,
                data: {
                    title: this.translate.instant('lang.linkContact'),
                    msg: this.translate.instant('lang.goToContact')
                }
            });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(async () => this.dialogRef.close(contactId)),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
