import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router, ActivatedRoute } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { FormBuilder, FormControl, FormGroup, Validators } from '@angular/forms';
import { finalize, tap, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { FunctionsService } from '@service/functions.service';
import { MatDialog } from '@angular/material/dialog';
@Component({
    templateUrl: 'attachment-type-administration.component.html'
})
export class AttachmentTypeAdministrationComponent implements OnInit {

    id: string;
    creationMode: boolean;
    loading: boolean = false;

    adminFormGroup: FormGroup;

    attachmentType: any = {
        typeId: new FormControl({ value: '', disabled: false }, [Validators.required]),
        label: new FormControl({ value: '', disabled: false }, [Validators.required]),
        visible: new FormControl({ value: true, disabled: false }),
        chrono: new FormControl({ value: true, disabled: false }),
        emailLink: new FormControl({ value: false, disabled: false }),
        signable: new FormControl({ value: false, disabled: false }),
        icon: new FormControl({ value: '', disabled: false }),
        versionEnabled: new FormControl({ value: false, disabled: false }),
        newVersionDefault: new FormControl({ value: false, disabled: false }),
        signedByDefault: new FormControl({ value: false, disabled: false})
    };

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private route: ActivatedRoute,
        private router: Router,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public functions: FunctionsService,
        public dialog: MatDialog,
        private _formBuilder: FormBuilder,
    ) {
    }

    ngOnInit(): void {

        this.adminFormGroup = this._formBuilder.group(this.attachmentType);
        this.loading = true;
        this.route.params.subscribe(async (params) => {
            this.id = params['id'];
            if (typeof params['id'] === 'undefined') {
                this.headerService.setHeader(this.translate.instant('lang.attachmentTypeCreation'));
                this.creationMode = true;
                this.loading = false;
            } else {
                this.creationMode = false;
                this.http.get(`../rest/attachmentsTypes/${this.id}`).pipe(
                    tap((data: any) => {
                        Object.keys(this.attachmentType).forEach(key => {
                            this.attachmentType[key].setValue(data[key] === null ? false : data[key]);
                            if (key === 'typeId') {
                                this.attachmentType[key].disable();
                            }
                            if (key === 'visible' && this.attachmentType['typeId'].value === 'signed_response') {
                                this.attachmentType[key].setValue(false);
                                this.attachmentType[key].disable();
                            }
                        });
                        this.headerService.setHeader(this.translate.instant('lang.attachmentTypeModification'), this.attachmentType.label.value);
                    }),
                    finalize(() => this.loading = false),
                    catchError((err: any) => {
                        this.notify.handleErrors(err);
                        return of(false);
                    })
                ).subscribe();
            }
        });
    }

    onSubmit() {
        if (this.creationMode) {
            this.create();
        } else {
            this.update();
        }
    }

    getOptions() {
        return Object.keys(this.attachmentType).filter((id: any) => typeof this.attachmentType[id].value === 'boolean');
    }

    formatData() {
        const formattedTag = {};
        Object.keys(this.attachmentType).forEach(element => {
            formattedTag[element] = this.attachmentType[element].value;
        });
        return formattedTag;
    }

    create() {
        this.http.post('../rest/attachmentsTypes', this.formatData()).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.attachmentTypeAdded'));
                this.router.navigate(['/administration/attachments/types']);
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    update() {

        this.http.put(`../rest/attachmentsTypes/${this.id}`, this.formatData()).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.attachmentTypeUpdated'));
                this.router.navigate(['/administration/attachments/types']);
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isValidToggle(id: string) {
        if (id === 'newVersionDefault' && !this.attachmentType['versionEnabled'].value) {
            this.attachmentType['newVersionDefault'].setValue(false);
            return false;
        } else if (id === 'signedByDefault' && this.attachmentType['typeId'].value === 'signed_response') {
            this.attachmentType['signedByDefault'].setValue(true);
            return false;
        } else {
            return true;
        }
    }
}
